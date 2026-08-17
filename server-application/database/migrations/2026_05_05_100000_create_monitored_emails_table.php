<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_emails', static function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('time_interval_id')->nullable();
            $table->string('email_client', 64)->default('unknown');
            $table->enum('direction', ['sent', 'received', 'unknown'])->default('unknown');
            $table->text('from_address')->nullable();
            $table->json('to_addresses')->nullable();
            $table->text('subject')->nullable();
            $table->text('body_excerpt')->nullable();
            $table->boolean('has_attachment')->default(false);
            $table->timestamp('email_datetime')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('time_interval_id')->references('id')->on('time_intervals')->nullOnDelete();

            $table->index(['user_id', 'email_datetime']);
            $table->index(['email_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_emails');
    }
};
