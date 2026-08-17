<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\CattrFormRequest;

class DailyEmployeeUsageReportRequest extends CattrFormRequest
{
    public function _authorize(): bool
    {
        return auth()->check();
    }

    public function _rules(): array
    {
        return [
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
            'users' => 'nullable|array',
            'users.*' => 'integer|exists:users,id',
            'projects' => 'nullable|array',
            'projects.*' => 'integer|exists:projects,id',
            'tasks' => 'nullable|array',
            'tasks.*' => 'integer|exists:tasks,id',
            'search' => 'nullable|string|max:255',
        ];
    }
}