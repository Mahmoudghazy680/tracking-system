<?php

namespace App\Http\Requests\Interval;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;
use App\Models\TimeInterval;

class IntervalTotalRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return $this->user()->can('viewAny', TimeInterval::class);
    }

    public function _rules(): array
    {
        return [
            'start_at' => 'required|date',
            'end_at' => 'required|date',
            'user_id' => 'required|integer|exists:users,id',
        ];
    }
}
