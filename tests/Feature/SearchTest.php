<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $title, string $html = '', array $attrs = []): Page
    {
        $page = Page::factory()->create($attrs + ['title' => $title, 'visibility' => Visibility::Public]);
        if ($html !== '') {
            $page->contents()->create(['html' => $html]);
        }

        return $page;
    }

    private function search(string $q, ?User $user = null)
    {
        return ($user ? $this->actingAs($user) : $this)->get('/search?q='.urlencode($q));
    }

    public function test_finds_by_title_and_body_with_prefixes(): void
    {
        $this->page('Server Setup', '<p>Install the rack</p>');
        $this->page('Holiday Rota', '<p>Who covers the servers in December</p>');

        $this->search('serv')->assertOk()->assertSeeInOrder(['Server Setup', 'Holiday Rota'])->assertSee('2 pages found');
        $this->search('rack install')->assertSee('Server Setup')->assertDontSee('Holiday Rota');
    }

    public function test_respects_visibility_and_trash(): void
    {
        $author = User::factory()->editor()->create();
        $this->page('Payroll Notes', '', ['visibility' => Visibility::Private, 'author_id' => $author->id]);
        $this->page('Payroll Archive')->delete();

        $this->search('payroll')->assertSee('No pages match')->assertDontSee('Payroll Notes');
        $this->search('payroll', $author)->assertSee('Payroll Notes')->assertDontSee('Payroll Archive');
    }

    public function test_snippet_escapes_html_and_marks_hits(): void
    {
        $this->page('Maths', '<p>Remember that a &lt; b &amp;&amp; <i>b</i> &gt; c when sorting</p>');

        $this->search('sorting')
            ->assertSee('a &lt; b &amp;&amp; b &gt; c when <mark>sorting</mark>', false)
            ->assertDontSee('<i>b</i>', false);
    }

    public function test_description_stands_in_when_only_the_title_matches(): void
    {
        $page = $this->page('Glossary');
        $page->contents()->create(['html' => '<p>Terms</p>', 'description' => 'Words we use']);

        $this->search('glossary')->assertSee('Words we use');
    }

    public function test_secret_names_are_not_searchable(): void
    {
        $secret = Secret::factory()->create(['name' => 'Treasury Vault']);
        $this->page('Finance', '<p>Use <wk-secret data-id="'.$secret->id.'">Treasury Vault</wk-secret></p>');

        $this->search('treasury')->assertSee('No pages match');
    }

    public function test_hostile_input_never_errors(): void
    {
        $this->page('Normal');

        foreach (['"', '*', '-', 'NEAR(', ')', 'a OR', 'title:x', '^', '""""', '%_', str_repeat('word ', 50)] as $q) {
            $this->search($q)->assertOk();
        }
    }

    public function test_empty_query_and_htmx_partial(): void
    {
        $this->get('/search')->assertOk()->assertSee('Type a word or two');

        $this->page('Server Setup');
        $this->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'search-results'])->get('/search?q=server')
            ->assertOk()->assertSee('Server Setup')->assertDontSee('<html', false);
    }

    public function test_paging(): void
    {
        foreach (range(1, 22) as $i) {
            $this->page(sprintf('Report %02d', $i));
        }

        $this->search('report')->assertSee('22 pages found')->assertSee('page=2');
    }

    public function test_reindex_command(): void
    {
        $this->page('Alpha', '<p><a href="/beta">b</a></p>');

        $this->artisan('wicket:reindex')
            ->expectsOutputToContain('Search: indexed 1 pages.')
            ->expectsOutputToContain('Indexed links for 1 pages.')
            ->assertSuccessful();
    }

    public function test_navbar_has_a_search_box(): void
    {
        $this->get('/login')->assertSee('action="'.route('search').'"', false);
    }
}
