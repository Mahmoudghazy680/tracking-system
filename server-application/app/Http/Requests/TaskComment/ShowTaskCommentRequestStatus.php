<?php

namespace App\Http\Requests\TaskComment;

use App\Http\Requests\TrackerFormRequest;
use App\Models\Status;
use App\Models\TaskComment;

class ShowTaskCommentRequestStatus extends TrackerFormRequest
{
    public function _authorize(): bool
    {
        return $this->user()->can('view', TaskComment::class);
    }

    public function _rules(): array
    {
        return [];
    }
}
