<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_payrolls', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->foreignId('work_activity_id')
                ->constrained('work_activities')
                ->restrictOnDelete();
            $table->foreignId('work_attendance_id')
                ->unique()
                ->constrained('work_attendances')
                ->restrictOnDelete();
            $table->foreignId('worker_id')
                ->constrained('workers')
                ->restrictOnDelete();
            $table->foreignId('work_type_id')
                ->constrained('work_types')
                ->restrictOnDelete();
            $table->string('rate_type');
            $table->decimal('rate_amount', 15, 2);
            $table->decimal('work_quantity', 15, 2)->nullable();
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);
            $table->string('payroll_status')->default('DRAFT');
            $table->string('payment_status')->default('UNPAID');
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('payment_notes')->nullable();
            $table->foreignId('budget_allocation_item_id')
                ->nullable()
                ->constrained('budget_allocation_items')
                ->restrictOnDelete();
            $table->foreignId('budget_realization_id')
                ->nullable()
                ->constrained('budget_realizations')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_payrolls');
    }
};
