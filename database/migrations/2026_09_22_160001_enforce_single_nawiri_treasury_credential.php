<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nawiri_treasury_credentials', function (Blueprint $table) {
            $table->enum('singleton_key', ['primary'])->default('primary')->after('id');
        });

        $latestId = DB::table('nawiri_treasury_credentials')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('id');

        if ($latestId !== null) {
            DB::table('nawiri_treasury_credentials')->where('id', '!=', $latestId)->delete();
        }

        Schema::table('nawiri_treasury_credentials', function (Blueprint $table) {
            $table->unique('singleton_key');
        });
    }

    public function down(): void
    {
        Schema::table('nawiri_treasury_credentials', function (Blueprint $table) {
            $table->dropUnique(['singleton_key']);
            $table->dropColumn('singleton_key');
        });
    }
};
