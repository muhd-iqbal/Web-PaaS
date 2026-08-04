<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_bytes')->default(0)->after('status');
            $table->unsignedInteger('file_count')->default(0)->after('storage_bytes');
            $table->timestamp('files_updated_at')->nullable()->after('file_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['storage_bytes', 'file_count', 'files_updated_at']);
        });
    }
};
