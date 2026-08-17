<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use App\Enums\ScreenshotsState;
use App\Models\User;
use App\Http\Requests\TrackerFormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class CreateUserRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function _rules(): array
    {
        return [
            'full_name' => 'required|string',
            'email' => 'required|email',
            'windows_username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'windows_username')],
            'domain_user' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'domain_user')],
            'user_language' => 'required',
            'password' => 'sometimes|required|min:6',
            'important' => 'bool',
            'active' => 'required|bool',
            'screenshots_state' => ['required', new Enum(ScreenshotsState::class)],
            'manual_time' => 'sometimes|required|bool',
            'screenshots_interval' => 'required|int|min:1|max:15',
            'computer_time_popup' => 'required|int|min:1',
            'timezone' => 'required|string',
            'role_id' => ['required', new Enum(Role::class)],
            'type' => 'required|string',
            'web_and_app_monitoring' => 'sometimes|required|bool',
        ];
    }
}
