<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\Role;
use App\Helpers\ReportHelper;
use App\Http\Requests\Reports\DailyEmployeeUsageReportRequest;
use App\Jobs\GenerateAndSendReport;
use App\Reports\DailyEmployeeUsageReportExport;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Settings;
use Throwable;

class DailyEmployeeUsageReportController
{
    public function __invoke(DailyEmployeeUsageReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $export = DailyEmployeeUsageReportExport::init(
            Carbon::parse($request->input('start_at'))
                ->setTimezone($companyTimezone)
                ->startOfDay(),
            Carbon::parse($request->input('end_at'))
                ->setTimezone($companyTimezone)
                ->endOfDay(),
            $companyTimezone,
            $request->input('users'),
            $request->input('projects'),
            $request->input('tasks'),
            $request->input('search'),
        );

        return responder()->success($export->grouped())->respond();
    }

    /**
     * @throws Throwable
     */
    public function download(DailyEmployeeUsageReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $job = new GenerateAndSendReport(
            DailyEmployeeUsageReportExport::init(
                Carbon::parse($request->input('start_at'))
                    ->setTimezone($companyTimezone)
                    ->startOfDay(),
                Carbon::parse($request->input('end_at'))
                    ->setTimezone($companyTimezone)
                    ->endOfDay(),
                $companyTimezone,
                $request->input('users'),
                $request->input('projects'),
                $request->input('tasks'),
                $request->input('search'),
            ),
            $request->user(),
            ReportHelper::getReportFormat($request),
        );

        app(Dispatcher::class)->dispatchSync($job);

        return responder()->success(['url' => $job->getPublicPath()])->respond();
    }
}