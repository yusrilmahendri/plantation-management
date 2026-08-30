<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->ulid('event_id')->unique();
            $table->string('event_type');
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->string('plantation_entity_public_id');
            $table->string('finance_entity_public_id')->nullable();
            $table->string('source_public_id');
            $table->json('payload');
            $table->string('status');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index('event_type');
            $table->index('source_public_id');
            $table->unique(['event_type', 'source_public_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_outbox');
    }
};
