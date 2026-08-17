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

class TopWebsitesReportExport extends AppReport implements FromCollection, WithMapping, ShouldAutoSize, WithHeadings, WithStyles
{
    use Exportable;

    public function __construct(
        private readonly Carbon  $startAt,
        private readonly Carbon  $endAt,
        private readonly string  $companyTimezone,
        private readonly ?array  $users    = null,
        private readonly ?array  $projects = null,
        private readonly ?string $search   = null,
    ) {}

    public function collection(): Collection
    {
        return $this->queryReport();
    }

    /**
     * Extract domain from a URL.
     * Returns the host portion (e.g. "www.youtube.com" → "youtube.com").
     */
    private static function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        // Strip leading "www."
        return preg_replace('/^www\./i', '', $host);
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
            ->whereNotNull('tas.url')
            ->where('tas.url', '!=', '')
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

        $durationExpression = TrackedApplicationJoin::durationExpression();

        $rawRows = $query
            ->selectRaw(
                "tas.url,
                 SUM({$durationExpression}) AS total_seconds,
                 COUNT(DISTINCT tas.id) AS pageview_count,
                 COUNT(DISTINCT ti.user_id) AS user_count",
                [$startAt, $endAt, $endAt, $endAt]
            )
            ->groupBy(['tas.url'])
            ->havingRaw('total_seconds > 0')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(static fn($row) => (array) $row);

        // Group raw URL rows by extracted domain (PHP side)
        $byDomain = [];
        foreach ($rawRows as $row) {
            $domain = self::extractDomain($row['url']);

            if (isset($byDomain[$domain])) {
                $byDomain[$domain]['total_seconds']  += (int) $row['total_seconds'];
                $byDomain[$domain]['pageview_count'] += (int) $row['pageview_count'];
                $byDomain[$domain]['user_count']      = max($byDomain[$domain]['user_count'], (int) $row['user_count']);
                $byDomain[$domain]['urls'][]          = [
                    'url'            => $row['url'],
                    'total_seconds'  => (int) $row['total_seconds'],
                    'pageview_count' => (int) $row['pageview_count'],
                ];
            } else {
                $byDomain[$domain] = [
                    'domain'         => $domain,
                    'total_seconds'  => (int) $row['total_seconds'],
                    'pageview_count' => (int) $row['pageview_count'],
                    'user_count'     => (int) $row['user_count'],
                    'urls'           => [
                        [
                            'url'            => $row['url'],
                            'total_seconds'  => (int) $row['total_seconds'],
                            'pageview_count' => (int) $row['pageview_count'],
                        ],
                    ],
                ];
            }
        }

        // Sort urls within each domain by total_seconds desc
        foreach ($byDomain as &$entry) {
            usort($entry['urls'], static fn($a, $b) => $b['total_seconds'] <=> $a['total_seconds']);
        }
        unset($entry);

        // Apply optional search filter on domain
        if (!empty($this->search)) {
            $search = mb_strtolower($this->search);
            $byDomain = array_filter(
                $byDomain,
                static fn($entry) => str_contains(mb_strtolower($entry['domain']), $search)
            );
        }

        // Sort domains by total_seconds desc
        usort($byDomain, static fn($a, $b) => $b['total_seconds'] <=> $a['total_seconds']);

        return collect(array_values($byDomain));
    }

    /**
     * Return grouped list for JSON endpoint.
     */
    public function list(): array
    {
        return $this->queryReport()->all();
    }

    // ── Maatwebsite\Excel export interface (flat rows for file export) ───────

    public function headings(): array
    {
        return [
            'Domain',
            'URL',
            'Pageviews',
            'Duration (seconds)',
            'Duration (HH:MM:SS)',
            'Users',
        ];
    }

    public function map($entry): array
    {
        $rows = [];
        $seconds  = (int) $entry['total_seconds'];
        $h        = intdiv($seconds, 3600);
        $m        = intdiv($seconds % 3600, 60);
        $s        = $seconds % 60;
        $duration = sprintf('%02d:%02d:%02d', $h, $m, $s);

        // Domain summary row
        $rows[] = [
            $entry['domain'],
            '',
            (int) $entry['pageview_count'],
            $seconds,
            $duration,
            (int) $entry['user_count'],
        ];

        // Per-URL detail rows
        foreach ($entry['urls'] as $u) {
            $us  = (int) $u['total_seconds'];
            $uh  = intdiv($us, 3600);
            $um  = intdiv($us % 3600, 60);
            $uss = $us % 60;
            $rows[] = [
                '',
                $u['url'],
                (int) $u['pageview_count'],
                $us,
                sprintf('%02d:%02d:%02d', $uh, $um, $uss),
                '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function getReportId(): string
    {
        return 'top_websites_report';
    }

    public function getLocalizedReportName(): string
    {
        return __('Top_Websites_Report');
    }
}
