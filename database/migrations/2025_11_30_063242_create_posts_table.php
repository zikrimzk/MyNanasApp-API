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
        Schema::create('posts', function (Blueprint $table) {
            $table->id('postID');
            // $table->dateTime('post_dateTime')->useCurrent();
            $table->string('post_images')->nullable();
            $table->text('post_caption')->nullable();
            $table->string('post_location')->nullable();
            $table->string('post_status')->default(1); // 1 = active, 0 = inactive
            $table->integer('post_views_count')->default(0);
            $table->integer('post_likes_count')->default(0);
            $table->string('post_type')->default('undefined'); // undefined, announcement, community

            $table->unsignedBigInteger('entID');
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
        Schema::dropIfExists('posts');
    }
};
