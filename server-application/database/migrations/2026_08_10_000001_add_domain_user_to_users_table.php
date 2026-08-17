<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->string('domain_user')->nullable()->unique()->after('windows_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropUnique('users_domain_user_unique');
            $table->dropColumn('domain_user');
        });
    }
};
