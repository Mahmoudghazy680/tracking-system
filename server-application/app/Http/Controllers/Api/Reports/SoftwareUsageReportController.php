<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\Role;
use App\Helpers\ReportHelper;
use App\Http\Requests\Reports\SoftwareUsageReportRequest;
use App\Jobs\GenerateAndSendReport;
use App\Reports\SoftwareUsageReportExport;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Settings;
use Throwable;

class SoftwareUsageReportController
{
    /**
     * @api             {post} /api/report/software-usage Software Usage Report
     * @apiDescription  Returns per-user per-application per-day usage aggregated from tracked_applications.
     *                  Access is restricted to Admins and Managers (Team Leads).
     *
     * @apiVersion      4.0.0
     * @apiName         SoftwareUsageReport
     * @apiGroup        Reports
     * @apiUse          AuthHeader
     * @apiPermission   admin
     * @apiPermission   manager
     *
     * @apiParam {String}   start_at          Start of the date range (ISO 8601).
     * @apiParam {String}   end_at            End of the date range (ISO 8601).
     * @apiParam {Array}    [users]           Optional list of user IDs to filter by.
     * @apiParam {Array}    [projects]        Optional list of project IDs to filter by.
     * @apiParam {Array}    [tasks]           Optional list of task IDs to filter by.
     * @apiParam {String}   [apps]            Optional partial app name / executable search string.
     *
     * @apiUse 400Error
     * @apiUse UnauthorizedError
     * @apiUse ForbiddenError
     */
    public function __invoke(SoftwareUsageReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $export = SoftwareUsageReportExport::init(
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
     * @api             {post} /api/report/software-usage/download Download Software Usage Report
     * @apiDescription  Downloads the software usage report as CSV, XLSX, or PDF.
     *                  Access is restricted to Admins and Managers (Team Leads).
     *
     * @apiVersion      4.0.0
     * @apiName         DownloadSoftwareUsageReport
     * @apiGroup        Reports
     * @apiUse          AuthHeader
     * @apiHeader       {String} Accept   Desired format: text/csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/pdf
     *
     * @apiSuccess {String} url  Public URL to the generated file.
     *
     * @apiUse 400Error
     * @apiUse UnauthorizedError
     * @apiUse ForbiddenError
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
            SoftwareUsageReportExport::init(
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
