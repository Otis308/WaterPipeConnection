<?php

require_once __DIR__ . '/../stubs.php';

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('game_level_id')->nullable()->constrained('game_levels')->nullOnDelete();
            $table->string('player_name', 60)->default('Guest');
            $table->enum('game_mode', ['player', 'pvp'])->default('player');
            $table->enum('map_option', ['random', 'custom'])->default('random');
            $table->string('map_size', 10)->default('3x8');
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('easy');
            $table->enum('result', ['win', 'lose', 'surrender'])->default('win');
            $table->unsignedInteger('score_earned')->default(0);
            $table->unsignedInteger('connected_pipes')->default(0);
            $table->unsignedInteger('steps_count')->default(0);
            $table->unsignedInteger('ai_retries')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('telemetry_nosql')->nullable();
            $table->timestamp('played_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('title', 120);
            $table->string('description');
            $table->unsignedInteger('min_score')->default(100);
            $table->string('badge_icon')->default('Images/CupSao.png');
            $table->timestamps();
        });

        Schema::create('achievement_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('achievement_id')->constrained('achievements')->onDelete('cascade');
            $table->timestamp('unlocked_at')->useCurrent();
            $table->primary(['user_id', 'achievement_id']);
        });

        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('pipe_categories')->onDelete('cascade');
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->decimal('price', 12, 2)->default(50000);
            $table->unsignedInteger('stock')->default(100);
            $table->string('pipe_skin_folder', 100)->default('OngXanh');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('order_date');
            $table->enum('payment_gateway', ['momo', 'bank'])->default('momo');
            $table->enum('status', ['completed', 'pending', 'cancelled'])->default('completed');
            $table->timestamps();
        });

        Schema::create('shop_order_details', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained('shop_orders')->onDelete('cascade');
            $table->foreignId('shop_item_id')->constrained('shop_items')->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->primary(['order_id', 'shop_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_details');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_items');
        Schema::dropIfExists('achievement_user');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('game_sessions');
    }
};