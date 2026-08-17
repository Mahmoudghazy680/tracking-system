<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TrackedApplicationJoin
{
    public static function latestForIntervalCondition(string $alias = 'ta'): string
    {
        return "{$alias}.id = (
            SELECT ta_lookup.id
            FROM tracked_applications ta_lookup
            WHERE (
                  ta_lookup.time_interval_id = ti.id
                  OR (
                      ta_lookup.user_id = ti.user_id
                      AND ta_lookup.created_at <= DATE_ADD(COALESCE(ti.end_at, NOW()), INTERVAL 5 SECOND)
                  )
              )
            ORDER BY
              CASE
                WHEN ta_lookup.time_interval_id = ti.id THEN 0
                WHEN ta_lookup.user_id = ti.user_id
                  AND ta_lookup.created_at BETWEEN DATE_SUB(ti.start_at, INTERVAL 5 SECOND)
                  AND DATE_ADD(COALESCE(ti.end_at, NOW()), INTERVAL 5 SECOND) THEN 1
                ELSE 2
              END,
              ta_lookup.created_at DESC,
              ta_lookup.id DESC
            LIMIT 1
        )";
    }

    public static function displayNameExpression(string $alias = 'ta'): string
    {
        return "COALESCE(NULLIF({$alias}.title, ''), NULLIF({$alias}.executable, ''), 'Unknown Application')";
    }

    public static function executableBaseNameExpression(string $alias = 'ta'): string
    {
        return "SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE({$alias}.executable, ''), '\\\\', -1), '/', -1)";
    }

    public static function normalizedExecutableExpression(string $alias = 'ta'): string
    {
        $baseNameExpression = self::executableBaseNameExpression($alias);

        return "LOWER(COALESCE(NULLIF({$baseNameExpression}, ''), ''))";
    }

    public static function programDisplayNameExpression(string $alias = 'ta'): string
    {
        $baseNameExpression = self::executableBaseNameExpression($alias);
        $displayNameExpression = self::displayNameExpression($alias);

        return "COALESCE(NULLIF({$baseNameExpression}, ''), {$displayNameExpression})";
    }

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
}
