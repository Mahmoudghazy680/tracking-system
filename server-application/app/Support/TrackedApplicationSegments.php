<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TrackedApplicationSegments
{
    public static function query(): Builder
    {
        return DB::query()
            ->from('tracked_applications as ta')
            ->selectRaw(
                "ta.id,
                 ta.title,
                 ta.executable,
                 ta.url,
                 ta.user_id,
                 ta.created_at,
                 CASE
                     WHEN LOWER(COALESCE(ta.executable, '')) LIKE '%lockapp.exe%' THEN 1
                     ELSE 0
                 END AS is_ignored_app,
                 LEAD(ta.created_at) OVER (
                     PARTITION BY ta.user_id
                     ORDER BY ta.created_at, ta.id
                 ) AS next_created_at"
            )
            ->whereNull('ta.deleted_at')
            ->whereNotNull('ta.user_id');
    }

    public static function joinOverlapCondition(string $alias = 'tas'): string
    {
        return "{$alias}.created_at < COALESCE(ti.end_at, NOW())
            AND COALESCE({$alias}.next_created_at, COALESCE(ti.end_at, NOW())) > ti.start_at";
    }

    public static function durationExpression(string $alias = 'tas'): string
    {
        return "TIMESTAMPDIFF(
            SECOND,
            GREATEST(ti.start_at, {$alias}.created_at, ?),
            LEAST(
                COALESCE(ti.end_at, ?),
                COALESCE({$alias}.next_created_at, COALESCE(ti.end_at, ?)),
                ?
            )
        )";
    }

    public static function displayNameExpression(string $alias = 'tas'): string
    {
        return "COALESCE(NULLIF({$alias}.title, ''), NULLIF({$alias}.executable, ''), 'Unknown Application')";
    }
}
