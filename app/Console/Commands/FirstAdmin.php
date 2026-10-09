<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FirstAdmin extends Command
{
    protected $signature = 'wicket:first-admin';

    protected $description = 'On a fresh install, create the first admin from WICKET_ADMIN_* env vars (container entrypoint)';

    public function handle(): int
    {
        if (User::where('role', Role::Admin)->exists()) {
            return self::SUCCESS;
        }

        $username = (string) (config('wicket.first_admin.username') ?: 'admin');
        $email = (string) config('wicket.first_admin.email');

        if ($email === '') {
            $this->components->warn('No admin yet. Set WICKET_ADMIN_EMAIL (and optionally WICKET_ADMIN_USERNAME / WICKET_ADMIN_PASSWORD) and restart, '
                .'or run: php artisan wicket:create-user <username> --role=admin');

            return self::SUCCESS;
        }

        $password = (string) config('wicket.first_admin.password');
        $generated = $password === '';
        if ($generated) {
            $password = Str::password(20, symbols: false);
        }

        $status = $this->call('wicket:create-user', [
            'username' => $username,
            '--name' => Str::headline($username),
            '--email' => $email,
            '--role' => Role::Admin->value,
            '--password' => $password,
        ]);

        if ($status === self::SUCCESS && $generated) {
            $this->components->warn("First admin '{$username}' password (shown once): {$password}");
        }

        return $status;
    }
}
