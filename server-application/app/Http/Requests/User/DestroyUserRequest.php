<?php

namespace App\Http\Requests\User;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Models\User;
use App\Http\Requests\TrackerFormRequest;

class DestroyUserRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return $this->user()->can('destroy', User::find(request('id')));
    }

    public function _rules(): array
    {
        return [
            'id' => 'required|int',
        ];
    }
}
