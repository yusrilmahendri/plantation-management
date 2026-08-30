<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantation_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantation_entity_id')
                ->constrained('plantation_entities')
                ->restrictOnDelete();
            $table->string('label')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantation_access_tokens');
    }
};
