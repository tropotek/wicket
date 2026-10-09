<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\PageLock;
use App\Models\User;
use App\Support\PageLocker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageLockTest extends TestCase
{
    use RefreshDatabase;

    private Page $page;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->editor()->create(['name' => 'Alice']);
        $this->bob = User::factory()->editor()->create(['name' => 'Bob']);
        $this->page = Page::factory()->for($this->alice, 'author')
            ->create(['slug' => 'locked', 'title' => 'Locked', 'visibility' => Visibility::Public]);
        Content::factory()->for($this->page)->for($this->alice)->create(['html' => '<p>v1</p>']);
    }

    private function savePayload(string $html = '<p>v2</p>'): array
    {
        return [
            'title' => 'Locked', 'slug' => 'locked', 'category' => '', 'visibility' => 'public',
            'publish' => '1', 'title_visible' => '1', 'html' => $html, 'description' => '', 'keywords' => '',
        ];
    }

    public function test_opening_the_editor_acquires_the_lock(): void
    {
        $this->freezeTime();

        $this->actingAs($this->alice)->get('/pages/locked/edit')->assertOk();

        $lock = PageLock::find($this->page->id);
        $this->assertSame($this->alice->id, $lock->user_id);
        $this->assertSame(now()->addSeconds(120)->toDateTimeString(), $lock->expires_at->toDateTimeString());
    }

    public function test_second_editor_sees_locked_notice(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->bob)->get('/pages/locked/edit')
            ->assertStatus(423)
            ->assertSee('Alice')
            ->assertDontSee('name="html"', false);

        $this->assertSame($this->alice->id, PageLock::find($this->page->id)->user_id);
    }

    public function test_reopening_refreshes_own_lock(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');
        $this->travel(90)->seconds();
        $this->actingAs($this->alice)->get('/pages/locked/edit')->assertOk();

        $this->assertEquals(now()->addSeconds(120)->toDateTimeString(), PageLock::find($this->page->id)->expires_at->toDateTimeString());
    }

    public function test_expired_lock_can_be_taken_over(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');
        $this->travel(121)->seconds();

        $this->actingAs($this->bob)->get('/pages/locked/edit')->assertOk();
        $this->assertSame($this->bob->id, PageLock::find($this->page->id)->user_id);
    }

    public function test_save_by_non_holder_is_refused(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->bob)->put('/pages/locked', $this->savePayload())->assertStatus(423);

        $this->assertCount(1, $this->page->contents()->get());
    }

    public function test_save_by_holder_saves_and_releases(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->alice)->put('/pages/locked', $this->savePayload())
            ->assertSessionHasNoErrors()->assertRedirect('/locked');

        $this->assertSame('<p>v2</p>', $this->page->fresh()->latestContent->html);
        $this->assertNull(PageLock::find($this->page->id));
    }

    public function test_save_without_a_lock_works_when_page_is_free(): void
    {
        $this->actingAs($this->bob)->put('/pages/locked', $this->savePayload())->assertRedirect('/locked');

        $this->assertNull(PageLock::find($this->page->id));
    }

    public function test_cancel_releases_lock(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->alice)->post('/pages/locked/unlock')->assertRedirect('/locked');

        $this->assertNull(PageLock::find($this->page->id));
    }

    public function test_non_holder_cannot_release(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->bob)->post('/pages/locked/unlock');

        $this->assertSame($this->alice->id, PageLock::find($this->page->id)->user_id);
    }

    public function test_heartbeat_extends_lock(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');
        $this->travel(100)->seconds();

        $this->actingAs($this->alice)->post('/pages/locked/lock')->assertOk()->assertDontSee('alert');

        $this->assertTrue(PageLock::find($this->page->id)->expires_at->greaterThan(now()->addSeconds(100)));
    }

    public function test_heartbeat_warns_when_lock_was_lost(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');
        $this->travel(121)->seconds();
        $this->actingAs($this->bob)->get('/pages/locked/edit');

        $this->actingAs($this->alice)->post('/pages/locked/lock')
            ->assertOk()
            ->assertSee('alert', false)
            ->assertSee('Bob');
    }

    public function test_restore_is_blocked_while_another_user_edits(): void
    {
        $old = $this->page->contents()->first();
        Content::factory()->for($this->page)->for($this->alice)->create(['html' => '<p>v2</p>']);
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->bob)->post("/pages/locked/revisions/{$old->id}/restore")
            ->assertSessionHas('status');

        $this->assertCount(2, $this->page->contents()->get());
    }

    public function test_page_view_shows_who_is_editing(): void
    {
        $this->actingAs($this->alice)->get('/pages/locked/edit');

        $this->actingAs($this->bob)->get('/locked')->assertSee('Being edited by Alice');
        $this->actingAs($this->alice)->get('/locked')->assertDontSee('Being edited by');
    }

    public function test_locker_allows_only_one_holder(): void
    {
        $locker = app(PageLocker::class);

        $this->assertNotNull($locker->acquire($this->page, $this->alice));
        $this->assertNull($locker->acquire($this->page, $this->bob));
        $this->assertNotNull($locker->acquire($this->page, $this->alice));
        $this->assertSame(1, PageLock::count());
    }

    public function test_locks_cascade_with_page_and_user(): void
    {
        $locker = app(PageLocker::class);
        $locker->acquire($this->page, $this->alice);
        $this->alice->delete();
        $this->assertSame(0, PageLock::count());

        $locker->acquire($this->page, $this->bob);
        $this->page->delete();
        $this->assertSame(0, PageLock::count());
    }
}
