<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });

        Schema::create('authentication_challenges', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 16);
            $table->string('credential_hash', 64);
            $table->string('device_name')->nullable();
            $table->boolean('remember')->default(false);
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authentication_challenges');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('two_factor_last_step'));
    }
};
