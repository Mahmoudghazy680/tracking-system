<?php

namespace App\Http\Requests\Interval;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;

class TrackAppRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return auth()->check();
    }

    public function _rules(): array
    {
        return [
            'title' => 'nullable|string',
            'executable' => 'nullable|string',
            'url' => 'nullable|string|max:2048',
        ];
    }
}
