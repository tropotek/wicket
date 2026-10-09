<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Html\Passes\SecretPlaceholders;
use App\Html\Pipeline;
use App\Html\RenderContext;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretPlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->editor()->create();
    }

    private function secret(array $attrs = []): Secret
    {
        return Secret::factory()->for($this->author, 'author')->create($attrs + [
            'name' => 'Bank Login',
            'password' => 'never-in-html-123',
            'visibility' => Visibility::Members,
        ]);
    }

    private function render(string $html, ?User $viewer): string
    {
        // The card's @can checks read the authenticated user, as on a real request.
        $viewer ? $this->actingAs($viewer) : auth()->logout();

        return (new Pipeline($this->app, [SecretPlaceholders::class]))->render($html, new RenderContext(null, $viewer));
    }

    private function placeholder(Secret|int|string $id): string
    {
        $id = $id instanceof Secret ? $id->id : $id;

        return '<p>Use <wk-secret data-id="'.$id.'" contenteditable="false">Bank Login</wk-secret> to pay.</p>';
    }

    private function assertCard(string $html, Secret $secret): void
    {
        $this->assertStringContainsString('x-data="secretCard(', $html);
        $this->assertStringContainsString(str_replace('/', '\/', route('secrets.reveal', $secret)), $html);
        $this->assertStringNotContainsString('<wk-secret', $html);
        $this->assertStringNotContainsString('never-in-html-123', $html);
    }

    private function assertHidden(string $html): void
    {
        $this->assertStringContainsString('wk-secret-hidden', $html);
        $this->assertStringNotContainsString('Bank Login', $html);
        $this->assertStringNotContainsString('<wk-secret', $html);
        $this->assertStringNotContainsString('secretCard', $html);
    }

    public function test_each_role_gets_the_card_or_the_hidden_chip(): void
    {
        $members = $this->secret(['visibility' => Visibility::Members]);
        $editors = $this->secret(['visibility' => Visibility::Editors]);
        $private = $this->secret(['visibility' => Visibility::Private]);

        $viewers = [
            'anon' => null,
            'guest' => User::factory()->guest()->create(),
            'member' => User::factory()->create(),
            'editor' => User::factory()->editor()->create(),
            'admin' => User::factory()->admin()->create(),
            'author' => $this->author,
        ];
        $sees = [
            'anon' => [],
            'guest' => [],
            'member' => ['members'],
            'editor' => ['members', 'editors'],
            'admin' => ['members', 'editors', 'private'],
            'author' => ['members', 'editors', 'private'],
        ];

        foreach ($viewers as $label => $viewer) {
            foreach (['members' => $members, 'editors' => $editors, 'private' => $private] as $kind => $secret) {
                $html = $this->render($this->placeholder($secret), $viewer);
                in_array($kind, $sees[$label], true) ? $this->assertCard($html, $secret) : $this->assertHidden($html);
                $this->assertStringContainsString('<p>Use ', $html, "{$label}/{$kind} keeps the surrounding text");
            }
        }
    }

    public function test_unpublished_secrets_are_hidden_from_everyone_but_author_and_admin(): void
    {
        $secret = $this->secret(['publish' => false]);

        $this->assertHidden($this->render($this->placeholder($secret), User::factory()->editor()->create()));
        $this->assertCard($this->render($this->placeholder($secret), $this->author), $secret);
    }

    public function test_deleted_bad_and_missing_ids_are_hidden(): void
    {
        $viewer = User::factory()->admin()->create();
        $secret = $this->secret();
        $secret->delete();

        foreach ([$secret->id, 'abc', '', '1 OR 1=1'] as $id) {
            $this->assertHidden($this->render($this->placeholder($id), $viewer));
        }
        $this->assertHidden($this->render('<wk-secret>Bank Login</wk-secret>', $viewer));
    }

    public function test_module_off_hides_everything(): void
    {
        config(['wicket.secrets' => false]);

        $this->assertHidden($this->render($this->placeholder($this->secret()), User::factory()->admin()->create()));
    }

    public function test_placeholders_are_loaded_in_one_query(): void
    {
        $a = $this->secret();
        $b = $this->secret(['name' => 'Other']);
        $viewer = User::factory()->admin()->create();
        $this->actingAs($viewer);

        DB::enableQueryLog();
        $this->render($this->placeholder($a).$this->placeholder($b).$this->placeholder($a), $viewer);
        $secretQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "secrets"'));

        $this->assertCount(1, $secretQueries);
    }

    public function test_page_and_revision_views_render_placeholders(): void
    {
        $secret = $this->secret();
        $page = Page::factory()->for($this->author, 'author')->create(['slug' => 'pay', 'visibility' => Visibility::Public]);
        $content = $page->contents()->create(['html' => $this->placeholder($secret)]);

        $this->get('/pay')->assertOk()->assertSee('wk-secret-hidden', false)->assertDontSee('Bank Login');
        $this->actingAs(User::factory()->create())->get('/pay')->assertSee('secretCard(', false)->assertDontSee('never-in-html-123');
        $this->actingAs($this->author)->get(route('pages.revision', [$page, $content]))
            ->assertOk()->assertSee('secretCard(', false)->assertDontSee('<wk-secret', false);
    }
}
