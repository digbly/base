<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('websites')) {
            return;
        }

        Schema::create('websites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('subdomain', 32)->unique();
            $table->string('domain', 64)->nullable()->unique();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('setup')->default(false);
            $table->boolean('is_demo')->default(false);
            $table->string('language', 10)->nullable();
            $table->string('theme', 10)->nullable();
            $table->string('database', 100)->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->uuid('user_id')->index();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        $userId = DB::table('users')->value('id');

        if ($userId !== null) {
            DB::table('websites')->insert([
                'id' => config('network.main_website_id'),
                'title' => 'Main Website',
                'domain' => config('network.domain'),
                'subdomain' => 'admin',
                'status' => 'active',
                'setup' => true,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
