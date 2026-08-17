<?php

namespace App\Http\Requests\Attachment;

use App\Helpers\AttachmentHelper;
use App\Http\Requests\AuthorizesAfterValidation;
use App\Http\Requests\TrackerFormRequest;

class CreateAttachmentRequest extends TrackerFormRequest
{
    use AuthorizesAfterValidation;

    public function authorizeValidated(): bool
    {
        return true;
    }

    public function _rules(): array
    {
        $maxFileSize = AttachmentHelper::getMaxAllowedFileSize();
        return [
            'attachment' => "file|required|max:$maxFileSize",
        ];
    }
}
