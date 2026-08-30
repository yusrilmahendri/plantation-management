<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_attendances', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('work_activity_id')
                ->constrained('work_activities')
                ->restrictOnDelete();
            $table->foreignId('worker_id')
                ->constrained('workers')
                ->restrictOnDelete();
            $table->string('attendance_status');
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->decimal('work_units', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['work_activity_id', 'worker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_attendances');
    }
};
