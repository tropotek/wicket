<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Admin create/update of a user. Route-level `role:admin` does the authorising.
 */
class UserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'username' => ['required', 'string', 'regex:'.User::USERNAME_PATTERN, 'max:64', Rule::unique(User::class)->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($id)],
            'role' => ['required', Rule::enum(Role::class)],
            'active' => ['required', 'boolean'],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Admins can't lock themselves out, and the last active admin can't be
     * demoted or deactivated.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $target = $this->route('user');

            if ($target === null || $validator->errors()->isNotEmpty()) {
                return;
            }

            $demoting = $this->input('role') !== Role::Admin->value;
            $deactivating = ! $this->boolean('active');

            if ($target->is($this->user())) {
                if ($this->input('role') !== $target->role->value) {
                    $validator->errors()->add('role', __('You cannot change your own role.'));
                }
                if ($deactivating) {
                    $validator->errors()->add('active', __('You cannot deactivate yourself.'));
                }

                return;
            }

            if ($target->isLastActiveAdmin() && ($demoting || $deactivating)) {
                $validator->errors()->add('role', __('This is the last active admin.'));
            }
        }];
    }
}
