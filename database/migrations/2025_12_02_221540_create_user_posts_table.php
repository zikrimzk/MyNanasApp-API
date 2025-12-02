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
        Schema::create('user_posts', function (Blueprint $table) {
            $table->id('userpostID');

            $table->boolean('is_liked')->default(false);
            $table->integer('userpost_status')->default(1); // 1 = active, 0 = inactive

            // FK
            $table->unsignedBigInteger('entID');
            $table->unsignedBigInteger('postID');

            $table->foreign('entID')
                ->references('entID')->on('users')
                ->onDelete('cascade');

            $table->foreign('postID')
                ->references('postID')->on('posts')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_posts');
    }
};
