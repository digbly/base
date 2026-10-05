<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('databases')) {
            return;
        }

        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->unsignedBigInteger('total_websites')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('databases');
    }
};
