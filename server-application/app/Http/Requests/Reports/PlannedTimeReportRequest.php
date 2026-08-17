<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\TrackerFormRequest;

class PlannedTimeReportRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return auth()->check();
    }

    public function _rules(): array
    {
        return [
            'projects' => 'nullable|exists:projects,id|array',
        ];
    }
}
