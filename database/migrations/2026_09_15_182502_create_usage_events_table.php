<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();

            /**
             * The event's own UTC time, not a row-creation timestamp. Rows are
             * immutable and are never updated, so `created_at`/`updated_at`
             * would only repeat this column, and pruning reads it directly.
             */
            $table->timestamp('occurred_at');

            $table->string('interface', 10);
            $table->string('event', 40);
            $table->string('outcome', 40)->nullable();
            $table->decimal('duration_ms', 12, 3)->nullable();
            $table->string('input_size_bucket', 20)->nullable();
            $table->string('diagnostic', 50)->nullable();
            $table->string('result_type', 20)->nullable();
            $table->string('api_version', 20)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('mcp_tool', 80)->nullable();
            $table->string('mcp_transport', 20)->nullable();
            $table->string('mcp_protocol_version', 20)->nullable();

            /**
             * Untrusted metadata, stored only after normalization and redaction.
             * A browser conversion is an XHR, so `request_url` holds the
             * transport URL (`/livewire/update`) and the page that hosted the
             * conversion arrives separately as `referrer_url`. No URL is ever
             * inferred from a request body.
             */
            $table->text('user_agent')->nullable();
            $table->text('referrer_url')->nullable();
            $table->text('request_url')->nullable();

            /**
             * Two legitimate events may share every recorded attribute and
             * timestamp, so there is deliberately no unique constraint. These
             * two indexes serve the pruning boundary and the only reporting
             * pattern this capability has; another one needs query evidence.
             */
            $table->index('occurred_at');
            $table->index(['interface', 'event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
