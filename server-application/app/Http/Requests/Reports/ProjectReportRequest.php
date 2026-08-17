<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\TrackerFormRequest;
use Filter;

class ProjectReportRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return auth()->check();
    }

    public function _rules(): array
    {
        return [
            'users' => 'nullable|exists:users,id|array',
            'projects' => 'nullable|exists:projects,id|array',
            'start_at' => 'required|date',
            'end_at' => 'required|date',
        ];
    }
}
