<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_allocation_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('finance_budget_allocation_id')
                ->constrained('finance_budget_allocations')
                ->restrictOnDelete();
            $table->string('category');
            $table->string('name');
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocation_items');
    }
};
