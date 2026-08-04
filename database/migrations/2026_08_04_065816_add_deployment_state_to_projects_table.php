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
            $table->string('hostname')->nullable()->unique()->after('slug');
            $table->string('url')->nullable()->after('hostname');
            $table->string('container_name')->nullable()->unique()->after('url');
            $table->timestamp('deployed_at')->nullable()->after('files_updated_at');
            $table->text('last_deployment_error')->nullable()->after('deployed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['hostname']);
            $table->dropUnique(['container_name']);
            $table->dropColumn(['hostname', 'url', 'container_name', 'deployed_at', 'last_deployment_error']);
        });
    }
};
