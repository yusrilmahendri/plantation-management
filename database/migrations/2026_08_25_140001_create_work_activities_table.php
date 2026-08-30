<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_activities', function (Blueprint $table) {
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
            $table->foreignId('work_type_id')
                ->constrained('work_types')
                ->restrictOnDelete();
            $table->date('activity_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('DRAFT');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_activities');
    }
};
