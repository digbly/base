<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_user')) {
            return;
        }

        Schema::create('website_user', function (Blueprint $table) {
            $table->uuid('user_id')->index();
            $table->uuid('website_id')->index();

            $table->primary(['user_id', 'website_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('website_id')
                ->references('id')
                ->on('websites')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_user');
    }
};
