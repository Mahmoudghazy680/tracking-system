<?php

namespace App\Http\Requests\Interval;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;
use App\Models\TimeInterval;

class PutScreenshotRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return true;
    }

    public function _rules(): array
    {
        return [
            'screenshot' => 'required|image',
        ];
    }
}
