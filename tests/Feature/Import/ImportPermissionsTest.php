<?php

namespace Tests\Feature\Import;

use App\Enums\Role;
use App\Enums\Visibility;
use App\Import\LegacyEncrypt;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

/** The tkwiki → Wicket permission mapping (table in _notes/laravel-rewrite/C3-import-permissions.md). */
class ImportPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a3f1c2d4e5b60718293a4b5c6d7e8f90112233445566778899aabbccddeeff00';

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

    private function user(string $type, int $permissions = 0, string $username = 'sam'): int
    {
        $id = $this->legacy->insert('user', ['type' => $type, 'given_name' => 'Sam']);
        $this->legacy->insert('auth', ['fkey' => 'App\Db\User', 'fid' => $id, 'username' => $username,
            'email' => $username.'@example.org', 'permissions' => $permissions, 'password' => password_hash('x', PASSWORD_BCRYPT)]);

        return $id;
    }

    public static function userCases(): array
    {
        return [
            'staff' => ['staff', 0, Role::Editor, null],
            'member' => ['member', 0, Role::Member, null],
            'admin bit, member type' => ['member', 0x1, Role::Admin, null],
            'admin bit, staff type' => ['staff', 0x1, Role::Admin, null],
            'sysadmin bit' => ['staff', 0x2, Role::Admin, null],
            'all bits' => ['member', 0x7, Role::Admin, null],
            'manage members, staff' => ['staff', 0x4, Role::Editor, 'could manage members'],
            'manage members, member' => ['member', 0x4, Role::Member, 'could manage members'],
            'unknown type' => ['', 0, Role::Member, 'unknown type'],
        ];
    }

    #[DataProvider('userCases')]
    public function test_user_roles(string $type, int $permissions, Role $role, ?string $warning): void
    {
        $this->user($type, $permissions);

        $run = $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true]);
        $warning ? $run->expectsOutputToContain($warning) : $run->doesntExpectOutputToContain('User "sam"');
        $run->assertSuccessful()->run();

        $this->assertSame($role, User::where('username', 'sam')->sole()->role);
    }

    public function test_page_and_secret_visibility(): void
    {
        $sam = $this->user('staff');
        $crypt = new LegacyEncrypt(self::KEY);
        $pages = [0 => Visibility::Public, 1 => Visibility::Members, 2 => Visibility::Editors, 9 => Visibility::Private, 5 => Visibility::Private];
        $secrets = [0 => Visibility::Members, 1 => Visibility::Members, 2 => Visibility::Editors, 9 => Visibility::Private, 5 => Visibility::Private];

        foreach (array_keys($pages) as $perm) {
            $this->legacy->insert('page', ['user_id' => $sam, 'title' => "P{$perm}", 'url' => "P{$perm}", 'permission' => $perm]);
            $this->legacy->insert('secret', ['user_id' => $sam, 'name' => "S{$perm}", 'permission' => $perm, 'password' => $crypt->encrypt('x')]);
        }

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--old-key' => self::KEY, '--no-interaction' => true])
            ->expectsOutputToContain('Page "P5" had unknown permission 5')
            ->expectsOutputToContain('Secret "S0" was public')
            ->expectsOutputToContain('Secret "S5" had unknown permission 5')
            ->assertSuccessful();

        foreach ($pages as $perm => $visibility) {
            $this->assertSame($visibility, Page::where('slug', "P{$perm}")->sole()->visibility, "page permission {$perm}");
        }
        foreach ($secrets as $perm => $visibility) {
            $this->assertSame($visibility, Secret::where('name', "S{$perm}")->sole()->visibility, "secret permission {$perm}");
        }
    }
}
