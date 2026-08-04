<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->unsignedBigInteger('bytes_sent')->default(0);
            $table->unsignedBigInteger('bytes_received')->default(0);
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamp('last_request_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'period_start']);
            $table->index('period_start');
        });

        Schema::create('monitoring_checkpoints', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('file_identity')->nullable();
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_resource_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sampled_at');
            $table->boolean('is_running')->default(false);
            $table->string('health', 32)->nullable();
            $table->decimal('cpu_percent', 8, 2)->nullable();
            $table->decimal('memory_percent', 8, 2)->nullable();
            $table->unsignedBigInteger('memory_usage_bytes')->nullable();
            $table->unsignedBigInteger('memory_limit_bytes')->nullable();
            $table->unsignedInteger('process_count')->nullable();
            $table->unsignedInteger('restart_count')->default(0);
            $table->boolean('oom_killed')->default(false);
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();
            $table->index(['project_id', 'sampled_at']);
            $table->index('sampled_at');
        });

        Schema::create('admin_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fingerprint')->unique();
            $table->string('type', 64)->index();
            $table->string('severity', 16)->index();
            $table->string('title');
            $table->text('message');
            $table->unsignedInteger('occurrences')->default(1);
            $table->json('context')->nullable();
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alerts');
        Schema::dropIfExists('project_resource_snapshots');
        Schema::dropIfExists('monitoring_checkpoints');
        Schema::dropIfExists('bandwidth_usages');
    }
};
