<?php

namespace App\Http\Requests\User;

use App\Http\Requests\TrackerFormRequest;
use App\Models\User;

class SendInviteUserRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function _rules(): array
    {
        return [
            'id' => 'required|int|exists:users,id'
        ];
    }
}
