<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('pages', [
            'id', 'title', 'slug', 'category', 'template', 'visibility', 'author_id',
            'publish', 'title_visible', 'views', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('contents', [
            'id', 'page_id', 'user_id', 'html', 'css', 'js', 'keywords', 'description', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('contents', 'updated_at'));
    }

    public function test_page_factory_defaults(): void
    {
        $page = Page::factory()->create();

        $this->assertNotEmpty($page->slug);
        $this->assertInstanceOf(User::class, $page->author);
        $this->assertTrue($page->publish);
        $this->assertTrue($page->title_visible);
        $this->assertSame(0, $page->views);
        $this->assertSame(Visibility::Public, $page->visibility);
        $this->assertNull($page->latestContent);
    }

    public function test_latest_content_is_the_newest_revision(): void
    {
        $page = Page::factory()->create();
        $this->freezeTime(function () use ($page) {
            Content::factory()->for($page)->create(['html' => '<p>one</p>']);
            Content::factory()->for($page)->create(['html' => '<p>two</p>']);
            Content::factory()->for($page)->create(['html' => '<p>three</p>']);
        });

        $this->assertSame('<p>three</p>', $page->fresh()->latestContent->html);
        $this->assertCount(3, $page->contents);
        $this->assertSame('<p>one</p>', $page->contents->first()->html);
    }

    public function test_latest_content_eager_loads(): void
    {
        Page::factory()->count(3)->withContent(2)->create();

        $pages = Page::with('latestContent')->get();

        $this->assertCount(3, $pages);
        $pages->each(fn (Page $p) => $this->assertSame(
            $p->contents()->max('id'),
            $p->latestContent->id,
        ));
    }

    public function test_force_deleting_page_cascades_revisions(): void
    {
        $page = Page::factory()->withContent(2)->create();

        $page->forceDelete();

        $this->assertDatabaseCount('contents', 0);
    }

    public function test_deleting_author_keeps_page_and_revisions(): void
    {
        $author = User::factory()->create();
        $page = Page::factory()->for($author, 'author')->create();
        Content::factory()->for($page)->for($author)->create();

        $author->delete();

        $this->assertNull($page->fresh()->author_id);
        $this->assertNull($page->contents()->first()->user_id);
    }

    public function test_slug_is_unique(): void
    {
        Page::factory()->create(['slug' => 'home']);

        $this->expectException(QueryException::class);
        Page::factory()->create(['slug' => 'home']);
    }

    public function test_author_and_views_are_not_mass_assignable(): void
    {
        $page = Page::factory()->create();
        $other = User::factory()->create();

        $page->update(['author_id' => $other->id, 'views' => 999, 'title' => 'Renamed']);

        $page->refresh();
        $this->assertSame('Renamed', $page->title);
        $this->assertNotSame($other->id, $page->author_id);
        $this->assertSame(0, $page->views);
    }
}
