<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDashboardPerformanceIndexes extends Migration
{
    public function up(): void
    {
        Schema::table('time_intervals', static function (Blueprint $table) {
            $table->index(
                ['deleted_at', 'end_at', 'start_at', 'user_id'],
                'time_intervals_dashboard_overlap_idx'
            );
            $table->index(
                ['user_id', 'start_at', 'end_at', 'deleted_at'],
                'time_intervals_dashboard_user_range_idx'
            );
        });

        Schema::table('tracked_applications', static function (Blueprint $table) {
            $table->index(
                ['time_interval_id', 'deleted_at', 'id'],
                'tracked_apps_interval_lookup_idx'
            );
            $table->index(
                ['user_id', 'created_at', 'deleted_at', 'id'],
                'tracked_apps_user_created_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tracked_applications', static function (Blueprint $table) {
            $table->dropIndex('tracked_apps_interval_lookup_idx');
            $table->dropIndex('tracked_apps_user_created_lookup_idx');
        });

        Schema::table('time_intervals', static function (Blueprint $table) {
            $table->dropIndex('time_intervals_dashboard_overlap_idx');
            $table->dropIndex('time_intervals_dashboard_user_range_idx');
        });
    }
}
