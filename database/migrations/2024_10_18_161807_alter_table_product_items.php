<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            // delete foreign key transaction_id column
            $table->dropForeign(['transaction_id']);
            // delete transaction_id column
            $table->dropColumn('transaction_id');

            // add transaction_detail_id column
            $table->foreignId('transaction_detail_id')->after('id')->nullable()->constrained('transaction_details')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_items', function (Blueprint $table) {
            // delete foreign key transaction_detail_id column
            $table->dropForeign(['transaction_detail_id']);
            // delete transaction_detail_id column
            $table->dropColumn('transaction_detail_id');

            // add transaction_id column
            $table->foreignId('transaction_id')->after('id')->nullable()->constrained('transactions')->cascadeOnDelete();
        });
    }
};
