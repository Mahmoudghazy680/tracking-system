<?php

namespace Tests\Feature\Reports;

use App\Models\Task;
use App\Models\TimeInterval;
use App\Models\User;
use App\Reports\DailyProgramSummaryReportExport;
use App\Reports\SoftwareUsageReportExport;
use App\Reports\TopProgramsReportExport;
use App\Reports\TopWebsitesReportExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Facades\ProjectFactory;
use Tests\Facades\TaskFactory;
use Tests\Facades\UserFactory;
use Tests\TestCase;

class UsageSegmentsTest extends TestCase
{
    private User $user;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = UserFactory::asUser()->create();
        $this->task = TaskFactory::forUser($this->user)->create();
    }

    public function test_reports_use_app_segments_and_ignore_lock_app(): void
    {
        $this->createInterval('2026-05-25 09:00:00', '2026-05-25 09:30:00');
        $this->trackApp('2026-05-25 09:00:00', 'Chrome', 'chrome.exe', 'https://example.com/work');
        $this->trackApp('2026-05-25 09:10:00', 'Excel', 'EXCEL.EXE');
        $this->trackApp('2026-05-25 09:20:00', 'Lock', 'C:\\Windows\\SystemApps\\LockApp.exe');

        $websites = TopWebsitesReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
        )->list();

        $this->assertCount(1, $websites);
        $this->assertSame('example.com', $websites[0]['domain']);
        $this->assertSame(600, $websites[0]['total_seconds']);

        $programs = collect(TopProgramsReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
        )->list());

        $this->assertSame(600, $programs->firstWhere('executable', 'chrome.exe')['total_seconds']);
        $this->assertSame(600, $programs->firstWhere('executable', 'EXCEL.EXE')['total_seconds']);
        $this->assertNull($programs->firstWhere('executable', 'C:\\Windows\\SystemApps\\LockApp.exe'));

        $software = SoftwareUsageReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
        )->grouped();

        $executables = collect($software[0]['software'])->pluck('duration_seconds', 'executable');
        $this->assertSame(600, $executables['chrome.exe']);
        $this->assertSame(600, $executables['EXCEL.EXE']);
        $this->assertArrayNotHasKey('C:\\Windows\\SystemApps\\LockApp.exe', $executables->all());
    }

    public function test_top_websites_groups_url_changes_by_domain(): void
    {
        $this->createInterval('2026-05-25 10:00:00', '2026-05-25 10:30:00');
        $this->trackApp('2026-05-25 10:00:00', 'Chrome', 'chrome.exe', 'https://www.example.com/a');
        $this->trackApp('2026-05-25 10:05:00', 'Chrome', 'chrome.exe', 'https://example.com/b');
        $this->trackApp('2026-05-25 10:15:00', 'Edge', 'msedge.exe', 'https://news.example.org/latest');

        $websites = collect(TopWebsitesReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
        )->list())->keyBy('domain');

        $this->assertSame(900, $websites['example.com']['total_seconds']);
        $this->assertSame(2, $websites['example.com']['pageview_count']);
        $this->assertSame(900, $websites['news.example.org']['total_seconds']);
        $this->assertSame(1, $websites['news.example.org']['pageview_count']);
    }

    public function test_top_program_filters_still_apply(): void
    {
        $otherProject = ProjectFactory::create();
        $otherTask = TaskFactory::forUser($this->user)->forProject($otherProject)->create();

        $this->createInterval('2026-05-25 11:00:00', '2026-05-25 11:10:00');
        $this->createInterval('2026-05-25 11:10:00', '2026-05-25 11:20:00', $otherTask);
        $this->trackApp('2026-05-25 11:00:00', 'Chrome', 'chrome.exe');
        $this->trackApp('2026-05-25 11:10:00', 'Excel', 'EXCEL.EXE');

        $programs = TopProgramsReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
            [$this->task->project_id],
            'chrome',
        )->list();

        $this->assertCount(1, $programs);
        $this->assertSame('chrome.exe', $programs[0]['executable']);
        $this->assertSame(600, $programs[0]['total_seconds']);
    }

    public function test_daily_program_summary_groups_browser_tabs_and_chat_titles_by_program(): void
    {
        $this->createInterval('2026-05-25 12:00:00', '2026-05-25 12:30:00');
        $this->trackApp('2026-05-25 12:00:00', 'Docs - Google Chrome', 'chrome.exe', 'https://example.com/docs');
        $this->trackApp('2026-05-25 12:05:00', 'Mail - Google Chrome', 'chrome.exe', 'https://mail.example.com/inbox');
        $this->trackApp('2026-05-25 12:15:00', 'Chat A | Microsoft Teams', 'teams.exe');
        $this->trackApp('2026-05-25 12:20:00', 'Chat B | Microsoft Teams', 'teams.exe');

        $summary = DailyProgramSummaryReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
        )->grouped();

        $this->assertCount(1, $summary);
        $this->assertSame(1800, $summary[0]['total_seconds']);
        $this->assertCount(1, $summary[0]['days']);
        $this->assertSame('2026-05-25', $summary[0]['days'][0]['date']);
        $this->assertSame(1800, $summary[0]['days'][0]['total_seconds']);

        $programs = collect($summary[0]['days'][0]['programs'])->pluck('duration_seconds', 'executable');

        $this->assertSame(900, $programs['chrome.exe']);
        $this->assertSame(900, $programs['teams.exe']);
        $this->assertCount(2, $programs);
    }

    public function test_daily_program_summary_filters_still_apply(): void
    {
        $otherProject = ProjectFactory::create();
        $otherTask = TaskFactory::forUser($this->user)->forProject($otherProject)->create();

        $this->createInterval('2026-05-25 13:00:00', '2026-05-25 13:10:00');
        $this->createInterval('2026-05-25 13:10:00', '2026-05-25 13:20:00', $otherTask);
        $this->trackApp('2026-05-25 13:00:00', 'Chrome', 'chrome.exe');
        $this->trackApp('2026-05-25 13:10:00', 'Excel', 'EXCEL.EXE');

        $summary = DailyProgramSummaryReportExport::init(
            Carbon::parse('2026-05-25 00:00:00'),
            Carbon::parse('2026-05-25 23:59:59'),
            'UTC',
            [$this->user->id],
            [$this->task->project_id],
            [$this->task->id],
            'chrome',
        )->grouped();

        $this->assertCount(1, $summary);
        $programs = $summary[0]['days'][0]['programs'];

        $this->assertCount(1, $programs);
        $this->assertSame('chrome.exe', $programs[0]['executable']);
        $this->assertSame(600, $programs[0]['duration_seconds']);
    }

    private function createInterval(string $startAt, string $endAt, ?Task $task = null): TimeInterval
    {
        return TimeInterval::create([
            'task_id' => ($task ?? $this->task)->id,
            'user_id' => $this->user->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'activity_fill' => 100,
            'mouse_fill' => 100,
            'keyboard_fill' => 100,
        ]);
    }

    private function trackApp(string $createdAt, string $title, string $executable, ?string $url = null): void
    {
        DB::table('tracked_applications')->insert([
            'title' => $title,
            'executable' => $executable,
            'url' => $url,
            'user_id' => $this->user->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
