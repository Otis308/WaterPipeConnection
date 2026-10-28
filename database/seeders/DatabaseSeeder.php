<?php

namespace Database\Seeders;

require_once __DIR__ . '/../stubs.php';

use App\Models\Achievement;
use App\Models\GameLevel;
use App\Models\PipeCategory;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Class DatabaseSeeder
 * Chỉ khởi tạo và lưu giữ duy nhất 1 tài khoản ADMIN (Đã xóa toàn bộ tài khoản Staff và User)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Chỉ giữ lại duy nhất 1 tài khoản Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@huit.edu.vn'],
            [
                'username'    => 'admin',
                'name'        => 'Admin',
                'password'    => Hash::make('password123'),
                'role'        => 'admin',
                'high_score'  => 1250,
                'total_games' => 25,
                'total_wins'  => 22,
                'coins'       => 9999,
                'is_active'   => true,
            ]
        );

        UserProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'birthday'       => '1990-05-15',
                'bio'            => null,
                'preferred_mode' => 'player',
            ]
        );

        // 2. Danh mục chủ đề ống nước
        $catBasic = PipeCategory::updateOrCreate(
            ['slug' => 'he-thong-ong-xanh-co-ban'],
            [
                'name'             => 'Hệ thống Ống Xanh Cơ Bản',
                'description'      => 'Các màn chơi tiêu chuẩn với ống xanh xoay 360 độ.',
                'score_multiplier' => 1.00,
            ]
        );

        // 3. Nhãn Tags
        $tagSwap = Tag::updateOrCreate(
            ['slug' => 'ong-vang'],
            ['name' => 'Ống Vàng (Swap Pipe)', 'color_hex' => '#eab308']
        );

        // 4. Màn chơi mẫu do Admin sở hữu
        $level = GameLevel::updateOrCreate(
            ['slug' => 'khoi-nguon-dong-chay-tan-phu'],
            [
                'category_id'        => $catBasic->id,
                'created_by'         => $admin->id,
                'title'              => 'Khởi nguồn Dòng chảy Tân Phú',
                'map_size'           => '3x8',
                'grid_rows'          => 3,
                'grid_cols'          => 8,
                'difficulty'         => 'easy',
                'time_limit_seconds' => 60,
                'reward_stars'       => 100,
                'description'        => 'Màn chơi mở đầu 3x8 giúp làm quen với thao tác xoay ống.',
                'is_published'       => true,
            ]
        );

        $level->tags()->syncWithoutDetaching([$tagSwap->id]);

        // 5. Thành tựu mẫu gán cho Admin
        $ach = Achievement::updateOrCreate(
            ['code' => 'FIRST_FLOW'],
            [
                'title'       => 'Kỹ Sư Đường Ống Tập Sự',
                'description' => 'Hoàn thành màn chơi nối ống nước đầu tiên.',
                'min_score'   => 100,
            ]
        );
        $admin->achievements()->syncWithoutDetaching([$ach->id]);
    }
}