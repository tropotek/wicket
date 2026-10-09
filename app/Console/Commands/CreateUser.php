<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'wicket:create-user
                            {username : Login username}
                            {--name= : Display name}
                            {--email= : Email address}
                            {--role=member : admin, editor, member or guest}
                            {--password= : Password (prompted when omitted)}';

    protected $description = 'Create an active user account';

    public function handle(): int
    {
        $data = [
            'username' => $this->argument('username'),
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => $this->option('email') ?? text('Email', required: true),
            'role' => $this->option('role'),
            'password' => $this->option('password') ?? password('Password', required: true),
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'alpha_dash:ascii', 'max:64', Rule::unique(User::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(collect($data)->except('role')->all());
        $user->forceFill([
            'role' => Role::from($data['role']),
            'active' => true,
            'email_verified_at' => now(),
        ])->save();

        $this->components->info("Created {$user->role->label()} '{$user->username}'.");

        return self::SUCCESS;
    }
}
