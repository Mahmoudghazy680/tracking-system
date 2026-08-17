<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\Role;
use App\Helpers\ReportHelper;
use App\Http\Requests\Reports\SoftwareUsageReportRequest;
use App\Jobs\GenerateAndSendReport;
use App\Reports\DailyProgramSummaryReportExport;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Settings;
use Throwable;

class DailyProgramSummaryReportController
{
    public function __invoke(SoftwareUsageReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $export = DailyProgramSummaryReportExport::init(
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
            $request->input('apps'),
        );

        return responder()->success($export->grouped())->respond();
    }

    /**
     * @throws Throwable
     */
    public function download(SoftwareUsageReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $job = new GenerateAndSendReport(
            DailyProgramSummaryReportExport::init(
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
                $request->input('apps'),
            ),
            $request->user(),
            ReportHelper::getReportFormat($request),
        );

        app(Dispatcher::class)->dispatchSync($job);

        return responder()->success(['url' => $job->getPublicPath()])->respond();
    }
}