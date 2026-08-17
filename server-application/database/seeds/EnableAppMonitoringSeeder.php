<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class EnableAppMonitoringSeeder extends Seeder
{
    /**
     * Enable web and app monitoring for all users.
     * This allows desktop clients to send application usage data.
     */
    public function run(): void
    {
        User::query()->update(['web_and_app_monitoring' => true]);
    }
}
