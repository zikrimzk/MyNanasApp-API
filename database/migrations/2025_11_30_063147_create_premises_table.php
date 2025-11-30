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
        Schema::create('premises', function (Blueprint $table) {
            $table->id('premiseID');
            $table->string('premise_type');
            $table->string('premise_name');
            $table->string('premise_address')->nullable();
            $table->string('premise_state')->nullable();
            $table->string('premise_city')->nullable();
            $table->string('premise_postcode')->nullable();
            $table->string('premise_landsize')->nullable();
            $table->string('premise_status')->default(1); // 1 = active, 0 = inactive
            $table->string('premise_coordinates')->nullable();

            $table->unsignedBigInteger('entID'); // FK

            $table->foreign('entID')
                ->references('entID')->on('users')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('premises');
    }
};
