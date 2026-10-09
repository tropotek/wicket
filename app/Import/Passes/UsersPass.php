<?php

namespace App\Import\Passes;

use App\Enums\Role;
use App\Import\ImportContext;
use App\Import\Pass;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** tkwiki `auth` + `user` → users. Password hashes are copied verbatim; Laravel rehashes them on login. */
class UsersPass implements Pass
{
    /** tkwiki User::PERM_ADMIN. */
    private const PERM_ADMIN = 0x1;

    public function name(): string
    {
        return 'Users';
    }

    public function count(ImportContext $context): int
    {
        return $this->query($context)->count();
    }

    public function run(ImportContext $context): int
    {
        $taken = [];
        $imported = 0;

        foreach ($this->query($context)->orderBy('a.auth_id')->get() as $row) {
            $username = $this->username((string) $row->username, $taken);
            if ($username !== $row->username) {
                $context->warn("User \"{$row->username}\" imported as \"{$username}\" (usernames allow letters, digits, . - _).");
            }

            $email = trim((string) $row->email);
            if ($email === '') {
                $email = $username.'@users.invalid';
                $context->warn("User \"{$username}\" had no email; set to {$email}.");
            }

            $password = (string) $row->password;
            if (password_get_info($password)['algo'] === null) {
                $password = Hash::make(Str::random(64));
                $context->warn("User \"{$username}\" had no usable password; they need to reset it.");
            }

            // Query builder, not the model: the `hashed` cast rejects hashes made with other options (cost 10).
            $id = DB::table('users')->insertGetId([
                'username' => $username,
                'name' => trim($row->given_name.' '.$row->family_name) ?: $username,
                'email' => $email,
                'password' => $password,
                'role' => $this->role((int) $row->permissions, (string) $row->type)->value,
                'active' => (bool) $row->active,
                'timezone' => $row->timezone ?: null,
                'last_login_at' => $row->last_login,
                'created_at' => $row->created ?? Carbon::now(),
                'updated_at' => $row->modified ?? Carbon::now(),
            ]);

            $context->map('user', (int) $row->user_id, $id);
            $imported++;
        }

        return $imported;
    }

    private function query(ImportContext $context): Builder
    {
        return $context->source->db()->table('auth as a')
            ->join('user as u', 'u.user_id', '=', 'a.fid')
            ->where('a.fkey', 'App\Db\User')
            ->select('a.username', 'a.password', 'a.email', 'a.timezone', 'a.active', 'a.permissions', 'a.last_login',
                'a.created', 'a.modified', 'u.user_id', 'u.type', 'u.given_name', 'u.family_name');
    }

    private function role(int $permissions, string $type): Role
    {
        return match (true) {
            ($permissions & self::PERM_ADMIN) !== 0 => Role::Admin,
            $type === 'staff' => Role::Editor,
            default => Role::Member,
        };
    }

    /** @param  array<string, true>  $taken  lower-cased usernames already used */
    private function username(string $legacy, array &$taken): string
    {
        $base = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $legacy) ?? '', '_') ?: 'user';
        $username = $base;
        for ($n = 2; isset($taken[strtolower($username)]); $n++) {
            $username = $base.'-'.$n;
        }
        $taken[strtolower($username)] = true;

        assert(preg_match(User::USERNAME_PATTERN, $username) === 1);

        return $username;
    }
}
