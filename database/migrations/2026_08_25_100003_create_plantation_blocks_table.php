<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantation_blocks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('plantation_id')
                ->constrained('plantations')
                ->restrictOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->decimal('area', 15, 2)->nullable();
            $table->string('crop_type')->nullable();
            $table->unsignedSmallInteger('planting_year')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plantation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantation_blocks');
    }
};
