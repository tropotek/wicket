<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'wicket:create-admin
                            {login : Username or email of the user to promote (a username is created if missing)}';

    protected $description = 'Promote a user to admin (and reactivate them), or create a new admin';

    public function handle(): int
    {
        $login = trim($this->argument('login'));
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        $user = $isEmail
            ? User::where('email', Str::lower($login))->first()
            : User::where('username', $login)->first();

        if ($user === null) {
            if ($isEmail) {
                $this->components->error("No user with email '{$login}'. Pass a username to create a new admin.");

                return self::FAILURE;
            }

            return $this->call('wicket:create-user', ['username' => $login, '--role' => Role::Admin->value]);
        }

        if ($user->isAdmin() && $user->active) {
            $this->components->info("'{$user->username}' is already an admin.");

            return self::SUCCESS;
        }

        $user->forceFill(['role' => Role::Admin, 'active' => true])->save();

        $this->components->info("Promoted '{$user->username}' to admin.");

        return self::SUCCESS;
    }
}
