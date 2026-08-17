<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\TrackerFormRequest;

class LoginRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return true;
    }

    public function _rules(): array
    {
        return [
            'email' => 'required',
            'password' => 'required',
            'recaptcha' => 'sometimes|nullable|string'
        ];
    }
}
