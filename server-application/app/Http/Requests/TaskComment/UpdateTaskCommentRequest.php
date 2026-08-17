<?php

namespace App\Http\Requests\TaskComment;

use App\Http\Requests\TrackerFormRequest;
use App\Models\Status;
use App\Models\TaskComment;
use App\Models\User;

class UpdateTaskCommentRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('update', TaskComment::class);
    }

    public function _rules(): array
    {
        return [
            'id' => 'required',
            'content' => 'required'
        ];
    }
}
