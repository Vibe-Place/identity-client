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
     * The failed ones: events a handler threw on, with the error, until
     * events:retry handles them.
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

        Schema::create('failed_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('subject');
            $table->jsonb('event');
            $table->string('handler')->nullable();
            $table->text('error');
            $table->text('trace');
            $table->timestampTz('failed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_events');
        Schema::dropIfExists('handled_events');
        Schema::dropIfExists('outbox_events');
    }
};
