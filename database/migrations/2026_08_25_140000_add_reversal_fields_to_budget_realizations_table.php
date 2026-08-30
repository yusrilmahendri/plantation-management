<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_realizations', function (Blueprint $table) {
            $table->string('status')->default('ACTIVE');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversed_reason')->nullable();
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->constrained('budget_realizations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_realizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn(['status', 'reversed_at', 'reversed_reason']);
        });
    }
};
