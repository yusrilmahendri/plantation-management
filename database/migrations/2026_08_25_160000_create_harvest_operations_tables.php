<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('harvests', function (Blueprint $table) {
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
            $table->foreignId('work_activity_id')
                ->nullable()
                ->constrained('work_activities')
                ->restrictOnDelete();
            $table->date('harvest_date');
            $table->string('commodity');
            $table->decimal('quantity', 15, 3);
            $table->string('unit');
            $table->unsignedInteger('bunch_count')->nullable();
            $table->string('quality_grade')->nullable();
            $table->text('notes')->nullable();
            $table->string('status');
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('harvest_sales', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->foreignId('buyer_id')
                ->constrained('buyers')
                ->restrictOnDelete();
            $table->date('sale_date');
            $table->string('invoice_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('adjustment_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('status');
            $table->string('payment_status');
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('harvest_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_sale_id')
                ->constrained('harvest_sales')
                ->cascadeOnDelete();
            $table->foreignId('harvest_id')
                ->constrained('harvests')
                ->restrictOnDelete();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
        });

        Schema::create('harvest_sale_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('harvest_sale_id')
                ->constrained('harvest_sales')
                ->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversed_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_sale_payments');
        Schema::dropIfExists('harvest_sale_items');
        Schema::dropIfExists('harvest_sales');
        Schema::dropIfExists('harvests');
        Schema::dropIfExists('buyers');
    }
};
