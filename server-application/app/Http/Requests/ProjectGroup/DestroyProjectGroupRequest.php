<?php

namespace App\Http\Requests\ProjectGroup;

use App\Http\Requests\TrackerFormRequest;
use App\Models\ProjectGroup;

class DestroyProjectGroupRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('destroy', ProjectGroup::class);
    }

    public function _rules(): array
    {
        return ['id' => 'required|int|exists:project_groups,id'];
    }
}
