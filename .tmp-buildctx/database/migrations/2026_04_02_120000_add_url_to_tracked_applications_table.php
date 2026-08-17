<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUrlToTrackedApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tracked_applications', function (Blueprint $table) {
            $table->text('url')->nullable()->after('executable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracked_applications', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
}
