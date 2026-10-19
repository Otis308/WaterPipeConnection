<?php

require_once __DIR__ . '/../stubs.php';

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipe_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->decimal('score_multiplier', 4, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('game_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('pipe_categories')
                  ->onDelete('cascade');
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->string('title', 150);
            $table->string('slug', 170)->unique();
            $table->string('map_size', 10)->default('3x8');
            $table->unsignedInteger('grid_rows')->default(3);
            $table->unsignedInteger('grid_cols')->default(8);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->unsignedInteger('time_limit_seconds')->default(60);
            $table->unsignedInteger('reward_stars')->default(100);
            $table->string('thumbnail')->nullable()->default('Images/NenNho.jpg');
            $table->text('description')->nullable();
            $table->json('map_config_json')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->string('color_hex', 15)->default('#01caee');
            $table->timestamps();
        });

        Schema::create('game_level_tag', function (Blueprint $table) {
            $table->foreignId('game_level_id')
                  ->constrained('game_levels')
                  ->onDelete('cascade');
            $table->foreignId('tag_id')
                  ->constrained('tags')
                  ->onDelete('cascade');
            $table->primary(['game_level_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_level_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('game_levels');
        Schema::dropIfExists('pipe_categories');
    }
};