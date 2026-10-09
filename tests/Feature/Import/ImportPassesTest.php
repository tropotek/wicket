<?php

namespace Tests\Feature\Import;

use App\Enums\Role;
use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\PageLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

class ImportPassesTest extends TestCase
{
    use RefreshDatabase;

    private LegacyFixture $legacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacy = new LegacyFixture;
    }

    protected function tearDown(): void
    {
        $this->legacy->drop();

        parent::tearDown();
    }

    private function legacyUser(string $type, string $username, array $auth = [], array $user = []): int
    {
        $id = $this->legacy->insert('user', $user + ['type' => $type, 'given_name' => ucfirst($username)]);
        $this->legacy->insert('auth', $auth + ['fkey' => 'App\Db\User', 'fid' => $id, 'username' => $username,
            'email' => $username.'@example.org', 'password' => password_hash('secret-'.$username, PASSWORD_BCRYPT, ['cost' => 10]),
            'timezone' => 'Australia/Melbourne']);

        return $id;
    }

    private function import(): void
    {
        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true])->assertSuccessful();
    }

    public function test_users_get_roles_and_keep_their_passwords(): void
    {
        $this->legacyUser('staff', 'godar', ['permissions' => 1, 'last_login' => '2026-10-08 04:44:13'], ['family_name' => 'Smith']);
        $this->legacyUser('staff', 'sam');
        $this->legacyUser('member', 'mo', ['active' => 0, 'timezone' => '']);

        $this->import();

        $godar = User::where('username', 'godar')->sole();
        $this->assertSame(Role::Admin, $godar->role);
        $this->assertSame('Godar Smith', $godar->name);
        $this->assertSame('Australia/Melbourne', $godar->timezone);
        $this->assertSame('2026-10-08 04:44:13', $godar->last_login_at->format('Y-m-d H:i:s'));
        $this->assertTrue(Hash::check('secret-godar', $godar->password), 'cost-10 bcrypt copied verbatim');

        $this->assertSame(Role::Editor, User::where('username', 'sam')->sole()->role);
        $mo = User::where('username', 'mo')->sole();
        $this->assertSame([Role::Member, false, null], [$mo->role, $mo->active, $mo->timezone]);
    }

    public function test_odd_usernames_emails_and_passwords_are_fixed_with_warnings(): void
    {
        $this->legacyUser('member', 'jo smith@home', ['password' => '', 'email' => '']);
        $this->legacyUser('member', 'jo_smith_home');

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true])
            ->expectsOutputToContain('jo smith@home')
            ->expectsOutputToContain('no usable password')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(['jo_smith_home', 'jo_smith_home-2'], User::pluck('username')->all());
        $renamed = User::where('username', 'jo_smith_home')->sole();
        $this->assertSame('jo_smith_home@users.invalid', $renamed->email);
        $this->assertFalse(Hash::check('', $renamed->password));
    }

    public function test_pages_and_revisions_come_over_with_links_rewritten(): void
    {
        $sam = $this->legacyUser('staff', 'sam');
        $home = $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Home', 'url' => 'Home', 'permission' => 0,
            'category' => 'Misc', 'views' => 42, 'title_visible' => 0, 'created' => '2023-06-18 06:56:23', 'modified' => '2024-01-02 03:04:05']);
        $notes = $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Wiki How To', 'url' => 'Wiki_How_To', 'permission' => 9]);
        $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Members', 'url' => 'Members_Only', 'permission' => 1]);
        $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Staff', 'url' => 'Staff_Only', 'permission' => 2]);
        $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Search', 'url' => 'search', 'permission' => 0]);

        $this->legacy->insert('content', ['page_id' => $home, 'user_id' => $sam, 'html' => '<p>First</p>', 'created' => '2023-06-18 06:56:23']);
        $this->legacy->insert('content', ['page_id' => $home, 'user_id' => $sam, 'keywords' => 'k', 'description' => 'd', 'css' => 'p{}',
            'html' => '<p><a href="page://Wiki_How_To" title="Wiki How To">how</a> <a href="page://search">s</a> <a href="page://Gone">gone</a></p><script>x()</script>']);
        $this->legacy->insert('content', ['page_id' => $notes, 'user_id' => $sam, 'html' => '<p><a href="page://Home">home</a></p>']);
        $this->legacy->insert('content', ['page_id' => 999, 'user_id' => $sam, 'html' => '<p>orphan</p>']);
        $this->legacy->insert('links', ['page_id' => $home, 'linked_id' => $notes]);

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true])
            ->expectsOutputToContain('"search" is reserved')
            ->expectsOutputToContain('page 999')
            ->expectsOutputToContain('Links: 1 in tkwiki, 4 rebuilt')
            ->assertSuccessful();

        $page = Page::where('slug', 'Home')->sole();
        $this->assertSame([Visibility::Public, 'Misc', 42, false, 'sam'],
            [$page->visibility, $page->category, $page->views, $page->title_visible, $page->author->username]);
        $this->assertSame('2023-06-18 06:56:23', $page->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2024-01-02 03:04:05', $page->updated_at->format('Y-m-d H:i:s'));

        $this->assertSame(Visibility::Private, Page::where('slug', 'Wiki_How_To')->sole()->visibility);
        $this->assertSame(Visibility::Members, Page::where('slug', 'Members_Only')->sole()->visibility);
        $this->assertSame(Visibility::Editors, Page::where('slug', 'Staff_Only')->sole()->visibility);
        $this->assertTrue(Page::where('slug', 'search-page')->exists());

        $this->assertSame(3, Content::count());
        $this->assertSame(['<p>First</p>', $page->contents->first()->created_at->format('Y-m-d H:i:s')],
            [$page->contents->first()->html, '2023-06-18 06:56:23']);

        $current = $page->latestContent;
        $this->assertSame(['k', 'd', 'p{}', 'sam'], [$current->keywords, $current->description, $current->css, $current->user->username]);
        $this->assertStringContainsString('<a href="/Wiki_How_To" title="Wiki How To" class="wk-page">how</a>', $current->html);
        $this->assertStringContainsString('<a href="/search-page" class="wk-page">s</a>', $current->html);
        $this->assertStringContainsString('<a href="/Gone" class="wk-page">gone</a>', $current->html);
        $this->assertStringNotContainsString('script', $current->html);

        $this->assertEqualsCanonicalizing(['Wiki_How_To', 'search-page', 'Gone'], PageLink::where('page_id', $page->id)->pluck('slug')->all());
        $this->get('/Home')->assertOk()->assertSee('href="/Wiki_How_To"', false);
    }

    public function test_links_to_the_old_site_become_paths(): void
    {
        config(['wicket.site_hosts' => ['tkwiki.example.org']]);
        $sam = $this->legacyUser('staff', 'sam');
        $home = $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Home', 'url' => 'Home', 'permission' => 0]);
        $this->legacy->insert('content', ['page_id' => $home, 'user_id' => $sam,
            'html' => '<p><a href="https://tkwiki.example.org/data/media/Mick/a.pdf">a</a> <a href="https://bt.example.org/x">bt</a></p>']);

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true])->assertSuccessful()->run();

        $this->assertSame('<p><a href="/data/media/Mick/a.pdf">a</a> <a href="https://bt.example.org/x">bt</a></p>',
            Page::where('slug', 'Home')->sole()->latestContent->html);
    }

    public function test_dry_run_counts_each_pass(): void
    {
        $sam = $this->legacyUser('staff', 'sam');
        $page = $this->legacy->insert('page', ['user_id' => $sam, 'title' => 'Home', 'url' => 'Home']);
        $this->legacy->insert('content', ['page_id' => $page, 'user_id' => $sam, 'html' => '<p>Hi</p>']);

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--dry-run' => true])
            ->expectsTable(['Pass', 'Found', 'Would import'], [['Users', 1, 1], ['Pages', 1, 1], ['Menu', 0, 0], ['Secrets', 0, 0], ['Revisions', 1, 1]])
            ->assertSuccessful();

        $this->assertSame(0, User::count() + Page::count());
    }
}
