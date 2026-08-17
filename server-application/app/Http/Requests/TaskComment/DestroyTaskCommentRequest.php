<?php

namespace App\Http\Requests\TaskComment;

use App\Http\Requests\TrackerFormRequest;
use App\Models\Status;
use App\Models\TaskComment;
use App\Models\User;

class DestroyTaskCommentRequest extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('destroy', TaskComment::class);
    }

    public function _rules(): array
    {
        return [
            'id' => 'required'
        ];
    }
}
