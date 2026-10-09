<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Secret;
use App\Support\SearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<int> page ids matching an FTS5 query */
    private function match(string $query): array
    {
        return array_map('intval', DB::table('pages_fts')->whereRaw('pages_fts MATCH ?', [$query])->orderBy('rowid')->pluck('rowid')->all());
    }

    private function row(Page $page): ?object
    {
        return DB::table('pages_fts')->where('rowid', $page->id)->first();
    }

    public function test_virtual_table_exists(): void
    {
        $this->assertNotNull(DB::selectOne("SELECT name FROM sqlite_master WHERE name = 'pages_fts' AND sql LIKE '%fts5%'"));
    }

    public function test_new_pages_and_revisions_are_indexed_as_text(): void
    {
        $page = Page::factory()->create(['title' => 'Server Room', 'category' => 'Ops']);
        $this->assertSame([$page->id], $this->match('server'));

        $page->contents()->create(['html' => '<h2>Cooling</h2><p>Check the <b>air</b>con</p><p>weekly</p>', 'description' => 'Rack notes', 'keywords' => 'hvac']);

        $row = $this->row($page);
        $this->assertSame('Cooling Check the aircon weekly', $row->body);
        $this->assertSame('Rack notes hvac', $row->meta);
        $this->assertSame([$page->id], $this->match('aircon'));
        $this->assertSame([], $this->match('h2'));
    }

    public function test_secret_names_and_scripts_are_never_indexed(): void
    {
        $secret = Secret::factory()->create(['name' => 'Treasury Vault']);
        $page = Page::factory()->create();
        $page->contents()->create(['html' => '<p>Login <wk-secret data-id="'.$secret->id.'">Treasury Vault</wk-secret> here</p><script>var hidden = 1</script><style>.x{}</style>']);

        $this->assertSame('Login here', $this->row($page)->body);
        $this->assertSame([], $this->match('treasury'));
        $this->assertSame([], $this->match('hidden'));
    }

    public function test_title_and_category_changes_reindex(): void
    {
        $page = Page::factory()->create(['title' => 'Old Title', 'category' => 'misc']);
        $page->update(['title' => 'Fresh Heading', 'category' => 'guides']);

        $this->assertSame([$page->id], $this->match('fresh'));
        $this->assertSame([$page->id], $this->match('category:guides'));
        $this->assertSame([], $this->match('old'));
    }

    public function test_trash_restore_and_purge(): void
    {
        $page = Page::factory()->create(['title' => 'Backup Plan']);
        $page->contents()->create(['html' => '<p>offsite tapes</p>']);

        $page->delete();
        $this->assertSame([], $this->match('backup'));

        $page->restore();
        $this->assertSame([$page->id], $this->match('tapes'));

        $page->forceDelete();
        $this->assertSame(0, DB::table('pages_fts')->count());
    }

    public function test_stemming_and_accents(): void
    {
        $page = Page::factory()->create(['title' => 'Café menus']);
        $page->contents()->create(['html' => '<p>Running the deployments</p>']);

        $this->assertSame([$page->id], $this->match('cafe'));
        $this->assertSame([$page->id], $this->match('run'));
        $this->assertSame([$page->id], $this->match('deployment'));
        $this->assertSame([$page->id], $this->match('menu'));
    }

    public function test_rebuild_refills_from_live_pages(): void
    {
        $live = Page::factory()->create(['title' => 'Alpha']);
        $trashed = Page::factory()->create(['title' => 'Beta']);
        $trashed->delete();
        DB::delete('DELETE FROM pages_fts');

        $this->assertSame(1, app(SearchIndex::class)->rebuild());
        $this->assertSame([$live->id], $this->match('alpha OR beta'));
    }
}
