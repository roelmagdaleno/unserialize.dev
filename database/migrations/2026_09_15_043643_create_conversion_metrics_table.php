<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('interface', 10);
            $table->string('outcome', 40);
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamp('last_occurred_at');
            $table->timestamps();

            /**
             * This index is the concurrency boundary, not a convenience. Two
             * simultaneous conversions with the same key must collide here so
             * the upsert increments the existing row instead of inserting a
             * second aggregate that would split the count.
             */
            $table->unique(['date', 'interface', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_metrics');
    }
};
