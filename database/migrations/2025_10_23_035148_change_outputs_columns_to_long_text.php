<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table( 'outputs', function ( Blueprint $table ) {
            $table->longText('serialized')->change();
            $table->longText('unserialized')->change();
        } );
    }

    public function down(): void {
        Schema::table( 'outputs', function ( Blueprint $table ) {
            $table->text('serialized')->change();
            $table->text('unserialized')->change();
        } );
    }
};
