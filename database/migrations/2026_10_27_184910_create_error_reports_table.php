<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_reports', function (Blueprint $table) {
            $table->id();

            /**
             * Reports are immutable, so this is the only time a row has; the
             * daily prune reads it directly.
             */
            $table->timestamp('reported_at')->index();

            $table->string('outcome', 40);
            $table->string('diagnostic', 50)->nullable();
            $table->json('input_traits')->nullable();

            /**
             * The failed value exactly as the person chose to send it. This is
             * the one table that holds submitted data, only with consent, and
             * only until the retention window passes.
             */
            $table->longText('input');
            $table->string('note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_reports');
    }
};
