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

class TopProgramsReportExport extends AppReport implements FromCollection, WithMapping, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    public function __construct(
        private readonly Carbon  $startAt,
        private readonly Carbon  $endAt,
        private readonly string  $companyTimezone,
        private readonly ?array  $users    = null,
        private readonly ?array  $projects = null,
        private readonly ?string $apps     = null,
    ) {}

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

        if (!empty($this->apps)) {
            $search = '%' . $this->apps . '%';
            $query->where(static function ($q) use ($search) {
                $q->where('tas.title', 'like', $search)
                  ->orWhere('tas.executable', 'like', $search);
            });
        }

        $appNameExpression = TrackedApplicationJoin::displayNameExpression('tas');
        $durationExpression = TrackedApplicationJoin::durationExpression();

        return $query
            ->selectRaw(
                "{$appNameExpression} AS app_name,
                 COALESCE(tas.executable, '') AS executable,
                 SUM({$durationExpression}) AS total_seconds,
                 COUNT(DISTINCT ti.id) AS interval_count,
                 COUNT(DISTINCT ti.user_id) AS user_count",
                [$startAt, $endAt, $endAt, $endAt]
            )
            ->groupBy(['app_name', 'executable'])
            ->havingRaw('total_seconds > 0')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(static fn($row) => [
                'app_name'      => $row->app_name,
                'executable'    => $row->executable,
                'total_seconds' => (int) $row->total_seconds,
                'interval_count'=> (int) $row->interval_count,
                'user_count'    => (int) $row->user_count,
            ]);
    }

    /**
     * Flat list of top programs sorted by total time.
     */
    public function list(): array
    {
        return $this->queryReport()->values()->all();
    }

    // ── Maatwebsite\Excel export interface ──────────────────────────────────

    public function headings(): array
    {
        return [
            'Application',
            'Executable',
            'Duration (seconds)',
            'Duration (HH:MM:SS)',
            'Intervals',
            'Users',
        ];
    }

    public function map($row): array
    {
        $seconds  = (int) $row['total_seconds'];
        $h        = intdiv($seconds, 3600);
        $m        = intdiv($seconds % 3600, 60);
        $s        = $seconds % 60;
        $duration = sprintf('%02d:%02d:%02d', $h, $m, $s);

        return [
            $row['app_name'],
            $row['executable'],
            $seconds,
            $duration,
            (int) $row['interval_count'],
            (int) $row['user_count'],
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
        return 'top_programs_report';
    }

    public function getLocalizedReportName(): string
    {
        return __('Top_Programs_Report');
    }
}
