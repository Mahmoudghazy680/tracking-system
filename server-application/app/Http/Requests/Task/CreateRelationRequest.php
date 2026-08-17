<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskRelationType;
use App\Http\Requests\AuthorizesAfterValidation;
use App\Models\Task;
use App\Http\Requests\TrackerFormRequest;
use Illuminate\Validation\Rule;

class CreateRelationRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return $this->user()->can('update', Task::find(request('task_id')))
            && $this->user()->can('update', Task::find(request('related_task_id')));
    }

    public function _rules(): array
    {
        return [
            'task_id' => [
                'required',
                'int',
                'different:related_task_id',
                Rule::exists('tasks', 'id'),
            ],
            'related_task_id' => [
                'required',
                'int',
                'different:task_id',
                Rule::exists('tasks', 'id'),
            ],
            'relation_type' => ['required', Rule::enum(TaskRelationType::class)],
        ];
    }
}
