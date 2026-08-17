<?php

namespace App\Http\Requests\Project;

use App\Http\Requests\AuthorizesAfterValidation;
use App\Models\Project;
use App\Http\Requests\TrackerFormRequest;

class DestroyProjectRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return $this->user()->can('destroy', Project::find(request('id')));
    }

    public function _rules(): array
    {
        return ['id' => 'required|int|exists:projects,id'];
    }
}
