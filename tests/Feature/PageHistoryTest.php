<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Page $page;

    private User $editor;

    /** @var list<Content> */
    private array $revs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->editor()->create(['name' => 'Ed Itor']);
        $this->page = Page::factory()->for($this->editor, 'author')
            ->create(['slug' => 'hist', 'visibility' => Visibility::Public]);

        foreach (['<p>first</p>', '<p>second</p>', '<p>third</p>'] as $html) {
            $this->revs[] = Content::factory()->for($this->page)->for($this->editor)->create(['html' => $html]);
        }
    }

    public function test_access(): void
    {
        $this->get('/pages/hist/history')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/pages/hist/history')->assertForbidden();
        $this->actingAs($this->editor)->get('/pages/hist/history')->assertOk();

        $this->page->update(['visibility' => Visibility::Private]);
        $this->actingAs(User::factory()->editor()->create())->get('/pages/hist/history')->assertForbidden();
    }

    public function test_history_lists_revisions_newest_first(): void
    {
        $this->actingAs($this->editor)->get('/pages/hist/history')
            ->assertOk()
            ->assertSeeInOrder(['#3', '#2', '#1'])
            ->assertSee('Ed Itor')
            ->assertSee(__('current'));
    }

    public function test_diff_shows_changes(): void
    {
        $this->actingAs($this->editor)
            ->get("/pages/hist/diff?from={$this->revs[0]->id}&to={$this->revs[2]->id}")
            ->assertOk()
            ->assertSee('&lt;p&gt;first&lt;/p&gt;', false)
            ->assertSee('&lt;p&gt;third&lt;/p&gt;', false)
            ->assertSee('table-danger', false)
            ->assertSee('table-success', false);
    }

    public function test_diff_defaults_to_previous_vs_latest(): void
    {
        $this->actingAs($this->editor)->get('/pages/hist/diff')
            ->assertOk()
            ->assertSee('&lt;p&gt;second&lt;/p&gt;', false)
            ->assertSee('&lt;p&gt;third&lt;/p&gt;', false)
            ->assertDontSee('&lt;p&gt;first&lt;/p&gt;', false);
    }

    public function test_revisions_from_another_page_are_rejected(): void
    {
        $foreign = Content::factory()->create();

        $this->actingAs($this->editor)->get("/pages/hist/revisions/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->editor)->post("/pages/hist/revisions/{$foreign->id}/restore")->assertNotFound();
        $this->actingAs($this->editor)
            ->get("/pages/hist/diff?from={$foreign->id}&to={$this->revs[2]->id}")
            ->assertNotFound();
    }

    public function test_revision_view_renders_old_content(): void
    {
        $this->actingAs($this->editor)->get("/pages/hist/revisions/{$this->revs[0]->id}")
            ->assertOk()
            ->assertSee('<p>first</p>', false)
            ->assertSee(__('Restore this revision'));
    }

    public function test_restore_appends_a_copy_by_the_restorer(): void
    {
        $other = User::factory()->editor()->create();

        $this->actingAs($other)->post("/pages/hist/revisions/{$this->revs[0]->id}/restore")
            ->assertRedirect(route('pages.show', 'hist'));

        $latest = $this->page->fresh()->latestContent;
        $this->assertCount(4, $this->page->contents()->get());
        $this->assertSame('<p>first</p>', $latest->html);
        $this->assertSame($other->id, $latest->user_id);
        $this->assertNotSame($this->revs[0]->id, $latest->id);
    }

    public function test_restoring_current_revision_is_a_no_op(): void
    {
        $this->actingAs($this->editor)->post("/pages/hist/revisions/{$this->revs[2]->id}/restore")
            ->assertRedirect();

        $this->assertCount(3, $this->page->contents()->get());
    }

    public function test_member_cannot_restore(): void
    {
        $this->actingAs(User::factory()->create())
            ->post("/pages/hist/revisions/{$this->revs[0]->id}/restore")
            ->assertForbidden();

        $this->assertCount(3, $this->page->contents()->get());
    }

    public function test_page_view_links_to_history_for_editors(): void
    {
        $this->actingAs($this->editor)->get('/hist')->assertSee('href="'.route('pages.history', 'hist').'"', false);
        $this->actingAs(User::factory()->create())->get('/hist')->assertDontSee(route('pages.history', 'hist'));
    }
}
