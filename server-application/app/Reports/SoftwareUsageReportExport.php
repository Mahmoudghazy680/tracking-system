<?php

namespace App\Reports;

use App\Contracts\AppReport;
use App\Support\TrackedApplicationJoin;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SoftwareUsageReportExport extends AppReport implements FromCollection, WithMapping, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    public function __construct(
        private readonly Carbon  $startAt,
        private readonly Carbon  $endAt,
        private readonly string  $companyTimezone,
        private readonly ?array  $users    = null,
        private readonly ?array  $projects = null,
        private readonly ?array  $tasks    = null,
        private readonly ?string $apps     = null,
    ) {}

    /**
     * Build aggregated software-usage rows grouped by user / app name / date.
     *
     * Returns a flat Collection where each element is:
     * [
     *   'user_id'          => int,
     *   'user_full_name'   => string,
     *   'user_email'       => string,
     *   'app_name'         => string,
     *   'executable'       => string,
    *   'url'              => string,
     *   'usage_date'       => string (Y-m-d),
     *   'duration_seconds' => int,
     *   'interval_count'   => int,
     * ]
     */
    public function collection(): Collection
    {
        return $this->queryReport();
    }

    private function queryReport(): Collection
    {
        $startAt = $this->startAt->copy()->setTimezone('UTC')->toDateTimeString();
        $endAt = $this->endAt->copy()->setTimezone('UTC')->toDateTimeString();

        $query = DB::table('time_intervals as ti')
            ->join('users as u', 'ti.user_id', '=', 'u.id')
            ->joinSub(TrackedApplicationJoin::query(), 'tas', function ($join) {
                $join->on('tas.user_id', '=', 'ti.user_id')
                    ->whereRaw(TrackedApplicationJoin::joinOverlapCondition());
            })
            ->leftJoin('tasks as t', 'ti.task_id', '=', 't.id')
            ->leftJoin('projects as p', 't.project_id', '=', 'p.id')
            ->whereNull('ti.deleted_at')
            ->where('tas.is_ignored_app', '=', 0)
                        ->where('ti.start_at', '<=', $endAt)
                        ->where(function ($q) use ($startAt) {
                $q->whereNull('ti.end_at')
                  ->orWhere('ti.end_at', '>=', $startAt);
            })
            ->where('tas.created_at', '<', $endAt)
            ->whereRaw(
                'COALESCE(tas.next_created_at, COALESCE(ti.end_at, ?)) > ?',
                [$endAt, $startAt]
            );

        if (!empty($this->users)) {
            $query->whereIn('ti.user_id', $this->users);
        }

        if (!empty($this->projects)) {
            $query->whereIn('t.project_id', $this->projects);
        }

        if (!empty($this->tasks)) {
            $query->whereIn('ti.task_id', $this->tasks);
        }

        if (!empty($this->apps)) {
            $search = '%' . $this->apps . '%';
            $query->where(static function ($q) use ($search) {
                $q->where('tas.title', 'like', $search)
                  ->orWhere('tas.executable', 'like', $search)
                  ->orWhere('tas.url', 'like', $search);
            });
        }

        $tz = $this->companyTimezone;
        $appNameExpression = TrackedApplicationJoin::displayNameExpression('tas');
        $durationExpression = TrackedApplicationJoin::durationExpression();

        return $query
            ->selectRaw(
                "ti.user_id,
                 u.full_name AS user_full_name,
                 u.email AS user_email,
                 {$appNameExpression} AS app_name,
                 COALESCE(tas.executable, '') AS executable,
                 COALESCE(tas.url, '') AS url,
                 DATE(CONVERT_TZ(GREATEST(ti.start_at, tas.created_at, ?), 'UTC', ?)) AS usage_date,
                 SUM({$durationExpression}) AS duration_seconds,
                 COUNT(DISTINCT ti.id) AS interval_count",
                [$startAt, $tz, $startAt, $endAt, $endAt, $endAt]
            )
            ->groupBy([
                'ti.user_id',
                'u.full_name',
                'u.email',
                'app_name',
                'executable',
                'url',
                'usage_date',
            ])
            ->havingRaw('duration_seconds > 0')
            ->orderBy('ti.user_id')
            ->orderBy('usage_date')
            ->orderByDesc('duration_seconds')
            ->get()
            ->map(static fn($row) => (array) $row);
    }

    /**
     * For the JSON endpoint, return rows grouped by user → app name.
     */
    public function grouped(): array
    {
        $rows = $this->queryReport();

        return $rows
            ->groupBy('user_id')
            ->map(static function (Collection $userRows) {
                $first = $userRows->first();
                return [
                    'user' => [
                        'id'        => $first['user_id'],
                        'full_name' => $first['user_full_name'],
                        'email'     => $first['user_email'],
                    ],
                    'total_seconds' => $userRows->sum('duration_seconds'),
                    'apps'          => $userRows
                        ->groupBy(static fn(array $row) => sprintf(
                            '%s::%s::%s',
                            $row['app_name'],
                            $row['executable'],
                            $row['url'] ?? ''
                        ))
                        ->map(static function (Collection $appRows) {
                            $first = $appRows->first();
                            return [
                                'app_name'      => $first['app_name'],
                                'executable'    => $first['executable'],
                                'url'           => $first['url'] ?? '',
                                'total_seconds' => $appRows->sum('duration_seconds'),
                                'days'          => $appRows
                                    ->map(static fn($r) => [
                                        'date'             => $r['usage_date'],
                                        'duration_seconds' => (int) $r['duration_seconds'],
                                        'interval_count'   => (int) $r['interval_count'],
                                    ])
                                    ->values(),
                            ];
                        })
                        ->values(),
                    'software'      => $userRows
                        ->groupBy(static fn(array $row) => sprintf(
                            '%s::%s::%s',
                            $row['app_name'],
                            $row['executable'],
                            $row['url'] ?? ''
                        ))
                        ->map(static function (Collection $appRows) {
                            $first = $appRows->first();
                            return [
                                'name'            => $first['app_name'],
                                'executable'      => $first['executable'],
                                'url'             => $first['url'] ?? '',
                                'duration_seconds'=> (int) $appRows->sum('duration_seconds'),
                            ];
                        })
                        ->sortByDesc('duration_seconds')
                        ->values(),
                ];
            })
            ->values()
            ->all();
    }

    // ── Maatwebsite\Excel export interface ──────────────────────────────────

    public function headings(): array
    {
        return [
            'User',
            'Email',
            'Application',
            'Executable',
            'URL',
            'Date',
            'Duration (seconds)',
            'Duration (HH:MM:SS)',
            'Intervals',
        ];
    }

    public function map($row): array
    {
        $seconds  = (int) $row['duration_seconds'];
        $h        = intdiv($seconds, 3600);
        $m        = intdiv($seconds % 3600, 60);
        $s        = $seconds % 60;
        $duration = sprintf('%02d:%02d:%02d', $h, $m, $s);

        return [
            $row['user_full_name'],
            $row['user_email'],
            $row['app_name'],
            $row['executable'],
            $row['url'] ?? '',
            $row['usage_date'],
            $seconds,
            $duration,
            (int) $row['interval_count'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function getReportId(): string
    {
        return 'software_usage_report';
    }

    public function getLocalizedReportName(): string
    {
        return __('Software_Usage_Report');
    }
}
