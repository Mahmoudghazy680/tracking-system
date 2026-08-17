<?php

namespace App\Http\Requests\Interval;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;
use App\Models\TimeInterval;

class EditTimeIntervalRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return $this->user()->can('update', TimeInterval::find(request('id')));
    }

    public function _rules(): array
    {
        return [
            'id' => 'required|int|exists:time_intervals,id',
        ];
    }
}
