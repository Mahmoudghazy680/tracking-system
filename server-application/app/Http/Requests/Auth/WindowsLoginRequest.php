<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\TrackerFormRequest;

class WindowsLoginRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return true;
    }

    public function _rules(): array
    {
        return [
            'windows_username' => 'nullable|required_without:domain_user|string|max:255',
            'domain_user' => 'nullable|required_without:windows_username|string|max:255',
            'device_secret' => 'required|string|max:255',
        ];
    }
}
