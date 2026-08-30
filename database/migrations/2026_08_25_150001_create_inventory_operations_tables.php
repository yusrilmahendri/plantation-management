<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_purchases', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->date('purchase_date');
            $table->string('invoice_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('status');
            $table->foreignId('budget_allocation_item_id')
                ->nullable()
                ->constrained('budget_allocation_items')
                ->restrictOnDelete();
            $table->foreignId('budget_realization_id')
                ->nullable()
                ->constrained('budget_realizations')
                ->restrictOnDelete();
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_purchase_id')
                ->constrained('inventory_purchases')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->string('movement_type');
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->date('movement_date');
            $table->string('source_type');
            $table->string('source_public_id');
            $table->foreignId('plantation_id')
                ->nullable()
                ->constrained('plantations')
                ->restrictOnDelete();
            $table->foreignId('plantation_block_id')
                ->nullable()
                ->constrained('plantation_blocks')
                ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plantation_entity_id', 'inventory_item_id']);
            $table->index(['source_type', 'source_public_id']);
        });

        Schema::create('material_usages', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->foreignId('plantation_id')
                ->constrained('plantations')
                ->restrictOnDelete();
            $table->foreignId('plantation_block_id')
                ->nullable()
                ->constrained('plantation_blocks')
                ->restrictOnDelete();
            $table->date('usage_date');
            $table->text('description')->nullable();
            $table->string('status');
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('material_usage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_usage_id')
                ->constrained('material_usages')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fertilizer_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->foreignId('plantation_id')
                ->constrained('plantations')
                ->restrictOnDelete();
            $table->foreignId('plantation_block_id')
                ->constrained('plantation_blocks')
                ->restrictOnDelete();
            $table->date('application_date');
            $table->foreignId('work_activity_id')
                ->nullable()
                ->constrained('work_activities')
                ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('status');
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fertilizer_application_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fertilizer_application_id')
                ->constrained('fertilizer_applications')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('dosage_per_plant', 15, 3)->nullable();
            $table->unsignedInteger('plant_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fertilizer_application_items');
        Schema::dropIfExists('fertilizer_applications');
        Schema::dropIfExists('material_usage_items');
        Schema::dropIfExists('material_usages');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_purchase_items');
        Schema::dropIfExists('inventory_purchases');
    }
};
