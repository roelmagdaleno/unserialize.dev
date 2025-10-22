<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table( 'outputs', function ( Blueprint $table ) {
            $table->dropColumn( 'syntax_highlighted' );
        } );
    }

    public function down(): void {
        Schema::table( 'outputs', function ( Blueprint $table ) {
            $table->text( 'syntax_highlighted' )->nullable();
        } );
    }
};
