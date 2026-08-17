<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Requests\Reports\DashboardStatsRequest;
use App\Support\TrackedApplicationJoin;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Settings;

class DashboardStatsController
{
    private const MAX_RANGE_DAYS = 31;
    private const SLOW_QUERY_THRESHOLD_MS = 2000;

    public function __invoke(DashboardStatsRequest $request): JsonResponse
    {
        $companyTimezone = Settings::scope('core')->get('timezone', 'UTC');
        $limit = (int) ($request->input('limit', 10));

        $startAt = Carbon::parse($request->input('start_at'))
            ->setTimezone($companyTimezone)
            ->startOfDay();

        $endAt = Carbon::parse($request->input('end_at'))
            ->setTimezone($companyTimezone)
            ->endOfDay();

        if ($startAt->diffInDays($endAt, false) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'end_at' => [sprintf('Dashboard stats date range cannot exceed %d days.', self::MAX_RANGE_DAYS)],
            ]);
        }

        $users = $request->input('users');
        $queryStartedAt = microtime(true);

        $baseIntervals = static function () use ($startAt, $endAt, $users) {
            $q = DB::table('time_intervals as ti')
                ->whereNull('ti.deleted_at')
                ->whereNotNull('ti.end_at')
                ->where('ti.start_at', '<=', $endAt)
                ->where('ti.end_at', '>=', $startAt);

            if (!empty($users)) {
                $q->whereIn('ti.user_id', $users);
            }

            return $q;
        };

        $totalActivitySeconds = (int) $baseIntervals()
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(SECOND, ti.start_at, ti.end_at)), 0) AS total')
            ->value('total');

        $appSegments = static function () use ($startAt, $endAt, $users) {
            $q = DB::table('time_intervals as ti')
                ->joinSub(TrackedApplicationJoin::query(), 'tas', static function ($join) {
                    $join->on('tas.user_id', '=', 'ti.user_id')
                        ->whereRaw(TrackedApplicationJoin::joinOverlapCondition('tas'));
                })
                ->whereNull('ti.deleted_at')
                ->whereNotNull('ti.end_at')
                ->where('tas.is_ignored_app', '=', 0)
                ->where('ti.start_at', '<=', $endAt)
                ->where('ti.end_at', '>=', $startAt)
                ->where('tas.created_at', '<', $endAt)
                ->whereRaw(
                    'COALESCE(tas.next_created_at, COALESCE(ti.end_at, ?)) > ?',
                    [$endAt, $startAt]
                );

            if (!empty($users)) {
                $q->whereIn('ti.user_id', $users);
            }

            return $q;
        };

        $durationExpression = TrackedApplicationJoin::durationExpression('tas');
        $durationBindings = static fn() => [$startAt, $endAt, $endAt, $endAt];

        $totalBrowsingSeconds = (int) $appSegments()
            ->whereNotNull('tas.url')
            ->where('tas.url', '!=', '')
            ->selectRaw(
                'COALESCE(SUM(' . $durationExpression . '), 0) AS total',
                $durationBindings()
            )
            ->value('total');

        $topPrograms = $appSegments()
            ->selectRaw(
                TrackedApplicationJoin::displayNameExpression('tas') . " AS app_name,
                 COALESCE(tas.executable, '') AS executable,
                 SUM({$durationExpression}) AS total_seconds",
                $durationBindings()
            )
            ->groupBy(['app_name', 'executable'])
            ->havingRaw('total_seconds > 0')
            ->orderByDesc('total_seconds')
            ->limit($limit)
            ->get()
            ->map(static fn($row) => [
                'app_name'     => $row->app_name,
                'executable'   => $row->executable,
                'total_seconds'=> (int) $row->total_seconds,
            ])
            ->all();

        $domainExpression = "LOWER(TRIM(LEADING 'www.' FROM SUBSTRING_INDEX(
            SUBSTRING_INDEX(
                CASE
                    WHEN LOCATE('://', tas.url) > 0 THEN SUBSTRING_INDEX(tas.url, '://', -1)
                    ELSE tas.url
                END,
                '/',
                1
            ),
            ':',
            1
        )))";

        $topWebsites = $appSegments()
            ->whereNotNull('tas.url')
            ->where('tas.url', '!=', '')
            ->selectRaw(
                "{$domainExpression} AS domain,
                 SUM({$durationExpression}) AS total_seconds",
                $durationBindings()
            )
            ->groupBy('domain')
            ->havingRaw('total_seconds > 0')
            ->orderByDesc('total_seconds')
            ->limit($limit)
            ->get()
            ->map(static fn($row) => [
                'domain'        => $row->domain,
                'total_seconds' => (int) $row->total_seconds,
            ])
            ->all();

        $topUsersByActivity = $baseIntervals()
            ->join('users as u', 'ti.user_id', '=', 'u.id')
            ->selectRaw(
                "ti.user_id,
                 u.full_name,
                 SUM(TIMESTAMPDIFF(SECOND, ti.start_at, ti.end_at)) AS total_seconds"
            )
            ->groupBy(['ti.user_id', 'u.full_name'])
            ->orderByDesc('total_seconds')
            ->limit($limit)
            ->get()
            ->map(static fn($row) => [
                'user_id'      => (int) $row->user_id,
                'full_name'    => $row->full_name,
                'total_seconds'=> (int) $row->total_seconds,
            ])
            ->all();

        $topUsersByBrowsing = $appSegments()
            ->join('users as u', 'ti.user_id', '=', 'u.id')
            ->whereNotNull('tas.url')
            ->where('tas.url', '!=', '')
            ->selectRaw(
                "ti.user_id,
                 u.full_name,
                 SUM({$durationExpression}) AS total_seconds",
                $durationBindings()
            )
            ->groupBy(['ti.user_id', 'u.full_name'])
            ->havingRaw('total_seconds > 0')
            ->orderByDesc('total_seconds')
            ->limit($limit)
            ->get()
            ->map(static fn($row) => [
                'user_id'      => (int) $row->user_id,
                'full_name'    => $row->full_name,
                'total_seconds'=> (int) $row->total_seconds,
            ])
            ->all();

        $responseData = [
            'total_activity_seconds' => $totalActivitySeconds,
            'total_browsing_seconds' => $totalBrowsingSeconds,
            'top_programs'           => $topPrograms,
            'top_websites'           => $topWebsites,
            'top_users_by_activity'  => $topUsersByActivity,
            'top_users_by_browsing'  => $topUsersByBrowsing,
        ];

        $queryDurationMs = (int) round((microtime(true) - $queryStartedAt) * 1000);
        if ($queryDurationMs >= self::SLOW_QUERY_THRESHOLD_MS) {
            Log::warning('dashboard-stats slow query', [
                'duration_ms' => $queryDurationMs,
                'start_at'    => $startAt->toDateTimeString(),
                'end_at'      => $endAt->toDateTimeString(),
                'limit'       => $limit,
                'user_count'  => is_array($users) ? count($users) : 0,
            ]);
        }

        return responder()->success($responseData)->respond();
    }
}
