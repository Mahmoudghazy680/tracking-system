<?php

namespace App\Http\Requests\Interval;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;
use App\Models\TimeInterval;

class BulkEditTimeIntervalRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        $timeIntervalIds = [];

        foreach (request('intervals') as $interval) {
            $timeIntervalIds[] = $interval['id'];
        }

        return $this->user()->can('bulkUpdate', [TimeInterval::class, $timeIntervalIds]);
    }

    public function _rules(): array
    {
        return [
            'intervals' => 'required|array',
            'intervals.*.id' => 'required|int|exists:time_intervals,id',
            'intervals.*.task_id' => 'required|int|exists:tasks,id'
        ];
    }
}
