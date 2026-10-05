<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->index();
            $table->string('theme', 100)->index();
            $table->text('value')->nullable();
            $table->string('website_id')->nullable()->index();

            $table->unique(['code', 'theme', 'website_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
