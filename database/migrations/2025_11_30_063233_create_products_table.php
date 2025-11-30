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
        Schema::create('products', function (Blueprint $table) {
            $table->id('productID');
            $table->string('product_name');
            $table->text('product_desc')->nullable();
            $table->integer('product_qty')->default(0);
            $table->string('product_unit')->nullable();
            $table->decimal('product_price', 10, 2)->default(0);
            $table->string('product_status')->default(1); // 1 = active, 0 = inactive
            $table->string('product_image')->nullable();
            // $table->timestamp('product_createdAt')->useCurrent();

            // FK
            $table->unsignedBigInteger('categoryID');
            $table->unsignedBigInteger('premiseID');

            $table->foreign('categoryID')
                ->references('categoryID')->on('product_categories')
                ->onDelete('cascade');

            $table->foreign('premiseID')
                ->references('premiseID')->on('premises')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
