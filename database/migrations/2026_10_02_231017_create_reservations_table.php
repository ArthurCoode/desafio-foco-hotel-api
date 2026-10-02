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
        Schema::create('reservations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('external_id')->nullable();
        $table->foreignId('hotel_id')->constrained()->restrictOnDelete();
        $table->foreignId('room_id')->constrained()->restrictOnDelete();

        $table->date('check_in');
        $table->date('check_out');

        $table->string('status')->default('confirmed');

        $table->decimal('subtotal', 10, 2);
        $table->decimal('discount', 10, 2)->default(0);
        $table->decimal('fees', 10, 2)->default(0);
        $table->decimal('total', 10, 2);

        $table->timestamps();

        $table->unique(['hotel_id', 'external_id']);
        $table->index(['room_id', 'check_in', 'check_out']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
