<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browser_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('remember_hash', 64)->nullable()->unique();
            $table->timestamp('remember_until')->nullable();
            $table->string('name', 255);
            $table->string('client_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('last_active_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browser_devices');
    }
};
