<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->char('currency', 3)->default('myr')->change();
        });

        DB::table('plans')->where('currency', 'usd')->update(['currency' => 'myr']);
    }

    public function down(): void
    {
        DB::table('plans')->where('currency', 'myr')->update(['currency' => 'usd']);

        Schema::table('plans', function (Blueprint $table): void {
            $table->char('currency', 3)->default('usd')->change();
        });
    }
};
