<?php

require_once __DIR__ . '/../stubs.php';

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('name', 100);
            $table->string('email', 120)->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'staff', 'user'])->default('user');
            $table->unsignedInteger('high_score')->default(0);
            $table->unsignedInteger('total_games')->default(0);
            $table->unsignedInteger('total_wins')->default(0);
            $table->unsignedInteger('coins')->default(500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->date('birthday')->nullable();
            $table->string('avatar')->default('Images/CupSao.png');
            $table->text('bio')->nullable();
            $table->string('preferred_mode', 20)->default('player');
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('music_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};