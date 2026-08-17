<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\Role;
use App\Http\Requests\Reports\EmailMonitoringReportRequest;
use App\Models\MonitoredEmail;
use Illuminate\Http\JsonResponse;

class EmailMonitoringReportController
{
    public function __invoke(EmailMonitoringReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $perPage   = (int) $request->input('per_page', 50);
        $startAt   = $request->input('start_at');
        $endAt     = $request->input('end_at');
        $users     = $request->input('users');
        $search    = $request->input('search');
        $direction = $request->input('direction');

        $query = MonitoredEmail::with('user:id,full_name,email')
            ->whereNull('deleted_at')
            ->whereBetween('email_datetime', [$startAt, $endAt]);

        if (!empty($users)) {
            $query->whereIn('user_id', $users);
        }

        if (!empty($direction)) {
            $query->where('direction', $direction);
        }

        if (!empty($search)) {
            $query->where(static function ($q) use ($search) {
                $q->where('subject', 'like', '%' . $search . '%')
                  ->orWhere('from_address', 'like', '%' . $search . '%')
                  ->orWhereJsonContains('to_addresses', $search);
            });
        }

        $result = $query
            ->orderByDesc('email_datetime')
            ->paginate($perPage);

        return responder()->success($result)->respond();
    }
}
