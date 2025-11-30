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
        Schema::create('users', function (Blueprint $table) {
            $table->id('entID');
            $table->string('ent_fullname');
            $table->string('ent_email')->unique();
            $table->string('ent_phoneNo')->unique();
            $table->string('ent_icNo')->unique();
            $table->date('ent_dob');
            $table->text('ent_bio')->nullable();
            $table->string('ent_profilePhoto')->nullable();
            $table->integer('ent_account_status')->default(1); // 1 = active, 0 = inactive
            $table->integer('ent_account_visibility')->default(1); // 1 = public, 0 = private
            $table->string('ent_business_name')->nullable();
            $table->string('ent_business_ssmNo')->nullable();
            $table->string('ent_username')->unique();
            $table->string('ent_password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
