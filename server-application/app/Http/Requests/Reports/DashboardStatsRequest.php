<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\TrackerFormRequest;

class DashboardStatsRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return auth()->check();
    }

    public function _rules(): array
    {
        return [
            'start_at' => 'required|date',
            'end_at'   => 'required|date|after_or_equal:start_at',
            'users'    => 'nullable|array',
            'users.*'  => 'integer|exists:users,id',
            'limit'    => 'nullable|integer|min:1|max:50',
        ];
    }
}
