<?php

namespace App\Http\Requests\ProjectGroup;

use App\Helpers\QueryHelper;
use App\Http\Requests\TrackerFormRequest;
use App\Models\ProjectGroup;

class ListProjectGroupRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('viewAny', ProjectGroup::class);
    }

    public function _rules(): array
    {
        return QueryHelper::getValidationRules();
    }
}
