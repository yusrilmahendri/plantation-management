<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->after('id');
        });

        DB::table('work_types')->whereNull('public_id')->orderBy('id')->each(function (object $row): void {
            DB::table('work_types')->where('id', $row->id)->update([
                'public_id' => (string) Str::ulid(),
            ]);
        });

        Schema::table('work_types', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_types', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
