<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\CattrFormRequest;

class EmailMonitoringReportRequest extends CattrFormRequest
{
    public function _authorize(): bool
    {
        return auth()->check();
    }

    public function _rules(): array
    {
        return [
            'start_at'   => 'required|date',
            'end_at'     => 'required|date|after_or_equal:start_at',
            'users'      => 'nullable|array',
            'users.*'    => 'integer|exists:users,id',
            'search'     => 'nullable|string|max:255',
            'direction'  => 'nullable|in:sent,received,unknown',
            'per_page'   => 'nullable|integer|min:5|max:200',
        ];
    }
}
