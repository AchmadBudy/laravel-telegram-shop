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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained()->onDelete('cascade');
            $table->integer('total_price');
            $table->string('status');
            $table->string('message_id');
            $table->string('payment_type')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_link')->nullable();
            $table->string('payment_qr')->nullable();
            $table->string('payment_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
