<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->index();
            $table->string('name', 100);
            $table->uuid('website_id')->nullable()->index();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['website_id', 'code']);
        });

        $websiteId = config('network.main_website_id');

        if ($websiteId !== null) {
            DB::table('languages')->insert([
                [
                    'code' => 'en',
                    'name' => 'English',
                    'website_id' => $websiteId,
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'code' => 'vi',
                    'name' => 'Vietnamese',
                    'website_id' => $websiteId,
                    'is_default' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
