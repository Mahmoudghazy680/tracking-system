<?php

namespace App\Http\Controllers\Api\Reports;

use App\Enums\Role;
use App\Helpers\ReportHelper;
use App\Http\Requests\Reports\TopProgramsReportRequest;
use App\Jobs\GenerateAndSendReport;
use App\Reports\TopProgramsReportExport;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Settings;
use Throwable;

class TopProgramsReportController
{
    public function __invoke(TopProgramsReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $export = TopProgramsReportExport::init(
            Carbon::parse($request->input('start_at'))
                ->setTimezone($companyTimezone)
                ->startOfDay(),
            Carbon::parse($request->input('end_at'))
                ->setTimezone($companyTimezone)
                ->endOfDay(),
            $companyTimezone,
            $request->input('users'),
            $request->input('projects'),
            $request->input('apps'),
        );

        return responder()->success($export->list())->respond();
    }

    /**
     * @throws Throwable
     */
    public function download(TopProgramsReportRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole([Role::ADMIN, Role::MANAGER])) {
            return responder()->error(403, 'Forbidden')->respond(403);
        }

        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');

        $job = new GenerateAndSendReport(
            TopProgramsReportExport::init(
                Carbon::parse($request->input('start_at'))
                    ->setTimezone($companyTimezone)
                    ->startOfDay(),
                Carbon::parse($request->input('end_at'))
                    ->setTimezone($companyTimezone)
                    ->endOfDay(),
                $companyTimezone,
                $request->input('users'),
                $request->input('projects'),
                $request->input('apps'),
            ),
            $request->user(),
            ReportHelper::getReportFormat($request),
        );

        app(Dispatcher::class)->dispatchSync($job);

        return responder()->success(['url' => $job->getPublicPath()])->respond();
    }
}
