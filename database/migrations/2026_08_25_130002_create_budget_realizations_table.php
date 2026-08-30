<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_realizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('budget_allocation_item_id')
                ->constrained('budget_allocation_items')
                ->restrictOnDelete();
            $table->string('source_type');
            $table->string('source_public_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('realization_date');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_public_id'], 'budget_realizations_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_realizations');
    }
};
