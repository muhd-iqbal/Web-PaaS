<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('provisioning');
            $table->string('database_name', 64)->unique();
            $table->string('username', 32)->unique();
            $table->text('password');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(3306);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('usage_checked_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'usage_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_databases');
    }
};
