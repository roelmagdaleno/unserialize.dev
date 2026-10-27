<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            /**
             * A list of `InputTrait` values: yes-or-no facts about how a browser
             * value was pasted, never any part of it.
             */
            $table->json('input_traits')->nullable()->after('diagnostic');
        });
    }

    public function down(): void
    {
        Schema::table('usage_events', function (Blueprint $table) {
            $table->dropColumn('input_traits');
        });
    }
};
