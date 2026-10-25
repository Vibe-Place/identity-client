<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The outbox: events written in the same transaction as the change they
     * tell about, published to NATS afterwards (events:relay). The inbox: ids
     * of the events this service has handled, so a redelivered one is skipped.
     */
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('subject');
            $table->jsonb('event');
            $table->timestampTz('created_at');
            $table->timestampTz('published_at')->nullable();

            $table->index('published_at');
        });

        Schema::create('handled_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('subject');
            $table->timestampTz('handled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handled_events');
        Schema::dropIfExists('outbox_events');
    }
};
