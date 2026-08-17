<?php

namespace App\Http\Requests\ProjectGroup;

use App\Helpers\QueryHelper;
use App\Http\Requests\TrackerFormRequest;
use App\Models\ProjectGroup;

class ShowProjectGroupRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('view', ProjectGroup::class);
    }

    public function _rules(): array
    {
        return array_merge(
            QueryHelper::getValidationRules(),
            ['id' => 'required|int|exists:project_groups,id'],
        );
    }
}
