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

class DailyEmployeeUsageReportExport extends AppReport implements FromCollection, WithMapping, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    public function __construct(
        private readonly Carbon $startAt,
        private readonly Carbon $endAt,
        private readonly string $companyTimezone,
        private readonly ?array $users = null,
        private readonly ?array $projects = null,
        private readonly ?array $tasks = null,
        private readonly ?string $search = null,
    ) {}

    public function collection(): Collection
    {
        return $this->queryReport();
    }

    private static function extractDomain(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST) ?: $url;

        return (string) preg_replace('/^www\./i', '', $host);
    }

    private static function normalizeProgramName(?string $name, ?string $executable = null): string
    {
        $normalizedName = trim((string) $name);

        if ($normalizedName !== '' && str_contains($normalizedName, '|')) {
            $segments = array_values(array_filter(array_map('trim', explode('|', $normalizedName))));

            if (!empty($segments)) {
                $normalizedName = (string) end($segments);
            }
        }

        if ($normalizedName !== '' && str_contains($normalizedName, ' - ')) {
            $segments = array_values(array_filter(array_map('trim', preg_split('/\s+-\s+/', $normalizedName) ?: [])));

            if (!empty($segments)) {
                $normalizedName = (string) end($segments);
            }
        }

        if ($normalizedName !== '') {
            return $normalizedName;
        }

        $executableName = trim((string) $executable);
        if ($executableName !== '') {
            $executableName = basename(str_replace('\\', '/', $executableName));
        }

        return $executableName !== '' ? $executableName : 'Unknown Application';
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
            ->where(function ($nestedQuery) use ($startAt) {
                $nestedQuery->whereNull('ti.end_at')
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

        if (!empty($this->search)) {
            $search = '%' . $this->search . '%';
            $query->where(static function ($nestedQuery) use ($search) {
                $nestedQuery->where('tas.title', 'like', $search)
                    ->orWhere('tas.executable', 'like', $search)
                    ->orWhere('tas.url', 'like', $search);
            });
        }

        $tz = $this->companyTimezone;
        $programNameExpression = TrackedApplicationJoin::displayNameExpression('tas');
        $durationExpression = TrackedApplicationJoin::durationExpression();

        $rawRows = $query
            ->selectRaw(
                "ti.user_id,
                 u.full_name AS user_full_name,
                 u.email AS user_email,
                 {$programNameExpression} AS program_name,
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
                'program_name',
                'url',
                'usage_date',
            ])
            ->havingRaw('duration_seconds > 0')
            ->orderBy('ti.user_id')
            ->orderBy('usage_date')
            ->orderByDesc('duration_seconds')
            ->get();

        $groupedRows = [];

        foreach ($rawRows as $row) {
            $url = (string) ($row->url ?? '');
            $domain = self::extractDomain($url);
            $isWebsite = $domain !== '';
            $activityType = $isWebsite ? 'website' : 'program';
            $activityName = $isWebsite
                ? $domain
                : self::normalizeProgramName((string) $row->program_name, (string) ($row->executable ?? ''));
            $key = implode('::', [
                (int) $row->user_id,
                (string) $row->usage_date,
                $activityType,
                mb_strtolower($activityName),
            ]);

            if (!isset($groupedRows[$key])) {
                $groupedRows[$key] = [
                    'user_id' => (int) $row->user_id,
                    'user_full_name' => (string) $row->user_full_name,
                    'user_email' => (string) $row->user_email,
                    'usage_date' => (string) $row->usage_date,
                    'activity_type' => $activityType,
                    'activity_name' => $activityName,
                    'duration_seconds' => 0,
                    'interval_count' => 0,
                ];
            }

            $groupedRows[$key]['duration_seconds'] += (int) $row->duration_seconds;
            $groupedRows[$key]['interval_count'] += (int) $row->interval_count;
        }

        return collect(array_values($groupedRows))
            ->sortBy([
                ['user_full_name', 'asc'],
                ['usage_date', 'desc'],
                ['duration_seconds', 'desc'],
            ])
            ->values();
    }

    public function grouped(): array
    {
        $rows = $this->queryReport();

        return $rows
            ->groupBy('user_id')
            ->map(static function (Collection $userRows) {
                $first = $userRows->first();

                return [
                    'user' => [
                        'id' => $first['user_id'],
                        'full_name' => $first['user_full_name'],
                        'email' => $first['user_email'],
                    ],
                    'total_seconds' => (int) $userRows->sum('duration_seconds'),
                    'days' => $userRows
                        ->groupBy('usage_date')
                        ->map(static function (Collection $dayRows, string $usageDate) {
                            return [
                                'date' => $usageDate,
                                'total_seconds' => (int) $dayRows->sum('duration_seconds'),
                                'activities' => $dayRows
                                    ->sortByDesc('duration_seconds')
                                    ->map(static fn(array $row) => [
                                        'activity_type' => $row['activity_type'],
                                        'activity_name' => $row['activity_name'],
                                        'duration_seconds' => (int) $row['duration_seconds'],
                                        'interval_count' => (int) $row['interval_count'],
                                    ])
                                    ->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values()
            ->all();
    }

    public function headings(): array
    {
        return [
            'User',
            'Email',
            'Date',
            'Type',
            'Activity',
            'Duration (seconds)',
            'Duration (HH:MM:SS)',
            'Intervals',
        ];
    }

    public function map($row): array
    {
        $seconds = (int) $row['duration_seconds'];
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return [
            $row['user_full_name'],
            $row['user_email'],
            $row['usage_date'],
            ucfirst($row['activity_type']),
            $row['activity_name'],
            $seconds,
            sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds),
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
        return 'daily_employee_usage_report';
    }

    public function getLocalizedReportName(): string
    {
        return __('Daily_Employee_Usage_Report');
    }
}