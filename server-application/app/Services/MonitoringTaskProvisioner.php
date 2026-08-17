<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\ScreenshotsState;
use App\Models\Priority;
use App\Models\Project;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;

class MonitoringTaskProvisioner
{
    private const PROJECT_NAME = 'Activity Monitoring';
    private const TASK_NAME = 'General Activity';

    public static function ensureForUser(User $user): void
    {
        $project = Project::withoutGlobalScopes()
            ->where('name', self::PROJECT_NAME)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$project) {
            $project = Project::withoutGlobalScopes()->create([
                'company_id' => $user->company_id,
                'name' => self::PROJECT_NAME,
                'description' => 'Auto-generated project used for background activity tracking.',
                'important' => 0,
                'source' => 'internal',
                'screenshots_state' => ScreenshotsState::OPTIONAL->value,
            ]);
        }

        $project->users()->syncWithoutDetaching([
            $user->id => ['role_id' => Role::USER->value],
        ]);

        $task = Task::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('task_name', self::TASK_NAME)
            ->first();

        if (!$task) {
            $priorityId = Priority::withoutGlobalScopes()->orderBy('id')->value('id');
            $statusId = Status::withoutGlobalScopes()->where('active', true)->orderBy('id')->value('id')
                ?? Status::withoutGlobalScopes()->orderBy('id')->value('id');

            if (!$priorityId || !$statusId) {
                return;
            }

            $maxRelativePosition = Task::withoutGlobalScopes()->max('relative_position') ?? 0;

            $task = Task::withoutGlobalScopes()->create([
                'project_id' => $project->id,
                'task_name' => self::TASK_NAME,
                'description' => 'Auto-generated task used for background activity tracking.',
                'assigned_by' => $user->id,
                'priority_id' => $priorityId,
                'status_id' => $statusId,
                'important' => 0,
                'relative_position' => $maxRelativePosition + 1,
            ]);
        }

        $task->users()->syncWithoutDetaching([$user->id]);
    }
}
