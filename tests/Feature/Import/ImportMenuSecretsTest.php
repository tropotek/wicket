<?php

namespace Tests\Feature\Import;

use App\Enums\MenuItemType;
use App\Enums\Visibility;
use App\Import\LegacyEncrypt;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

#[Group('import')]
class ImportMenuSecretsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a3f1c2d4e5b60718293a4b5c6d7e8f90112233445566778899aabbccddeeff00';

    private LegacyFixture $legacy;

    private int $sam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacy = new LegacyFixture;
        $this->sam = $this->legacy->insert('user', ['type' => 'staff', 'given_name' => 'Sam']);
        $this->legacy->insert('auth', ['fkey' => 'App\Db\User', 'fid' => $this->sam, 'username' => 'sam', 'email' => 'sam@example.org',
            'password' => password_hash('x', PASSWORD_BCRYPT)]);
    }

    protected function tearDown(): void
    {
        $this->legacy->drop();

        parent::tearDown();
    }

    private function page(string $url): int
    {
        return $this->legacy->insert('page', ['user_id' => $this->sam, 'title' => str_replace('_', ' ', $url), 'url' => $url]);
    }

    private function secret(array $row): int
    {
        $crypt = new LegacyEncrypt(self::KEY);
        foreach (['url', 'username', 'password', 'otp', 'keys', 'notes'] as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = $crypt->encrypt($row[$field]);
            }
        }

        return $this->legacy->insert('secret', $row + ['user_id' => $this->sam, 'permission' => 9]);
    }

    private function import(array $options = []): PendingCommand
    {
        return $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true] + $options);
    }

    public function test_menu_tree_comes_over_with_parents_and_order(): void
    {
        $linux = $this->page('Linux');
        $git = $this->page('Git_Notes');
        $dev = $this->legacy->insert('menu_item', ['parent_id' => null, 'type' => 'dropdown', 'name' => 'Dev', 'order_id' => 10]);
        $this->legacy->insert('menu_item', ['parent_id' => $dev, 'page_id' => $git, 'type' => 'item', 'name' => 'Git', 'order_id' => 12]);
        $this->legacy->insert('menu_item', ['parent_id' => $dev, 'type' => 'divider', 'name' => '{divider}', 'order_id' => 11]);
        $this->legacy->insert('menu_item', ['parent_id' => $dev, 'page_id' => $linux, 'type' => 'item', 'name' => 'Linux', 'order_id' => 3]);
        $this->legacy->insert('menu_item', ['parent_id' => null, 'page_id' => $linux, 'type' => 'item', 'name' => 'Top', 'order_id' => 1]);
        $this->legacy->insert('menu_item', ['parent_id' => $dev, 'page_id' => 999, 'type' => 'item', 'name' => 'Gone', 'order_id' => 13]);

        $this->import()->expectsOutputToContain('Menu item "Gone"')->assertSuccessful();

        $dropdown = MenuItem::whereNull('parent_id')->where('name', 'Dev')->sole();
        $this->assertSame(MenuItemType::Dropdown, $dropdown->type);
        $this->assertSame([['Linux', 'Linux'], ['{divider}', null], ['Git', 'Git_Notes']],
            $dropdown->children->map(fn (MenuItem $m) => [$m->name, $m->page?->slug])->all());
        $this->assertSame(['Top', 'Dev'], MenuItem::whereNull('parent_id')->orderBy('sort')->pluck('name')->all());
    }

    public function test_secrets_are_re_encrypted_and_placeholders_resolved(): void
    {
        $bank = $this->secret(['name' => 'Bank', 'url' => 'https://bank.test', 'username' => 'me', 'password' => 'hunter2',
            'otp' => 'JBSWY3DPEHPK3PXP', 'notes' => "line1\nline2", 'created' => '2023-01-02 03:04:05']);
        $this->secret(['name' => 'Club', 'permission' => 1, 'publish' => 0]);
        $this->secret(['name' => 'Open', 'permission' => 0]);
        $hash = md5($bank.'Secret');

        $page = $this->page('Accounts');
        $this->legacy->insert('content', ['page_id' => $page, 'user_id' => $this->sam,
            'html' => '<p>Bank: <img class="wk-secret" data-secret-hash="'.$hash.'" title="Bank" src="/html/assets/img/secretbg.png"></p>']);

        $this->import(['--old-key' => self::KEY])
            ->expectsOutputToContain('Secret "Open" was public')
            ->assertSuccessful();

        $secret = Secret::where('name', 'Bank')->sole();
        $this->assertSame(['https://bank.test', 'me', 'hunter2', 'JBSWY3DPEHPK3PXP', null, "line1\nline2"],
            [$secret->url, $secret->username, $secret->password, $secret->otp, $secret->keys, $secret->notes]);
        $this->assertSame([Visibility::Private, true, 'sam', '2023-01-02 03:04:05'],
            [$secret->visibility, $secret->publish, $secret->author->username, $secret->created_at->format('Y-m-d H:i:s')]);
        $this->assertNotSame('hunter2', DB::table('secrets')->where('id', $secret->id)->value('password'), 'stored encrypted');

        $club = Secret::where('name', 'Club')->sole();
        $this->assertSame([Visibility::Members, false], [$club->visibility, $club->publish]);
        $this->assertSame(Visibility::Members, Secret::where('name', 'Open')->sole()->visibility);

        $html = Page::where('slug', 'Accounts')->sole()->latestContent->html;
        $this->assertSame('<p>Bank: <wk-secret data-id="'.$secret->id.'" contenteditable="false">Bank</wk-secret></p>', $html);
    }

    public function test_a_wrong_key_fails_before_anything_is_written(): void
    {
        $this->secret(['name' => 'Bank', 'password' => 'hunter2']);
        User::factory()->create(['username' => 'keepme']);

        $this->import(['--old-key' => str_repeat('0', 64), '--force' => true])
            ->expectsOutputToContain('does not decrypt')
            ->assertFailed();
        $this->import(['--old-key' => 'nope', '--force' => true])
            ->expectsOutputToContain('hex string')
            ->assertFailed();

        $this->assertSame(['keepme'], User::pluck('username')->all());
        $this->assertSame(0, Secret::count());
    }

    public function test_without_a_key_secrets_are_skipped_and_placeholders_become_text(): void
    {
        $bank = $this->secret(['name' => 'Bank', 'password' => 'hunter2']);
        $page = $this->page('Accounts');
        $this->legacy->insert('content', ['page_id' => $page, 'user_id' => $this->sam,
            'html' => '<p>Bank: <img class="wk-secret" data-secret-hash="'.md5($bank.'Secret').'" title="Bank"></p>']);

        $this->import()->expectsOutputToContain('Skipped 1 secret')->assertSuccessful();

        $this->assertSame(0, Secret::count());
        $this->assertSame('<p>Bank: Bank</p>', Page::where('slug', 'Accounts')->sole()->latestContent->html);
    }
}
