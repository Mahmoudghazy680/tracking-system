<?php

namespace App\Http\Requests\Task;

use App\Helpers\QueryHelper;
use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;

class ListTaskRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return true;
    }

    public function _rules(): array
    {
        return array_merge(QueryHelper::getValidationRules(), [
            'project_id' => 'sometimes|array',
        ]);
    }
}
