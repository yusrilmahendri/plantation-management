<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_rate', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plantation_entity_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_types');
    }
};
