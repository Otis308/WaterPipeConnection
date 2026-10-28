<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        $currentUser = Auth::user() ? Auth::user()->toArray() : null;
        return view('auth.login', compact('currentUser'));
    }

    public function login(Request $request)
    {
        $identifier = $request->input('username', $request->input('identifier', ''));
        $password   = $request->input('password', '');

        if ($identifier === '' || $password === '') {
            return back()->with('flash_error', 'Vui lòng nhập đầy đủ Tên đăng nhập/Email và Mật khẩu.');
        }

        $fieldType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$fieldType => $identifier, 'password' => $password])) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Kiểm tra tài khoản có bị khóa không
            if ($user->is_active != 1) {
                Auth::logout();
                return back()->with('flash_error', 'Tài khoản này đã bị khóa. Vui lòng liên hệ quản trị viên.');
            }

            // Admin → chuyển thẳng sang trang Admin
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard')->with('flash_success', 'Đăng nhập thành công! Chào mừng, ' . $user->name . '.');
            }

            // User thường → trang chủ game
            return redirect('/')->with('flash_success', 'Đăng nhập thành công!');
        }

        return back()->with('flash_error', 'Tên đăng nhập/Email hoặc mật khẩu không chính xác.');
    }

    public function register(Request $request)
    {
        // Xác thực dữ liệu đầu vào bằng Validate của Laravel thay vì check thủ công
        $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', 
        ], [
            'required'  => 'Vui lòng nhập đầy đủ thông tin.',
            'min'       => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'confirmed' => 'Mật khẩu xác nhận không khớp.',
            'unique'    => 'Tên đăng nhập hoặc email đã tồn tại.'
        ]);

        $user = User::create([
            'username'    => $request->username,
            'name'        => $request->username,
            'email'       => strtolower($request->email),
            'password'    => Hash::make($request->password),
            'role'        => 'user',
            'high_score'  => 0,
            'total_games' => 0,
            'total_wins'  => 0,
            'coins'       => 500,
            'is_active'   => true,
        ]);

        UserProfile::create([
            'user_id'        => $user->id,
            'bio'            => 'Người chơi hệ thống Game Nối Ống Nước.',
            'preferred_mode' => 'player',
        ]);

        Auth::login($user);

        return redirect('/')->with('flash_success', 'Đăng ký và đăng nhập thành công!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }

    public function quickSwitchRole(string $username)
    {
        $db = new \App\Services\PdoDatabaseService();
        $user = $db->findUserByUsernameOrEmail($username);
        if (!$user) {
            $_SESSION['flash_error'] = "Không tìm thấy tài khoản [{$username}] để chuyển đổi.";
            return redirect('/admin');
        }

        $_SESSION['huit_auth_user'] = [
            'id'         => (int)$user['id'],
            'username'   => $user['username'],
            'name'       => $user['name'] ?? $user['username'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'high_score' => (int)($user['high_score'] ?? 0),
            'total_games'=> (int)($user['total_games'] ?? 0),
            'total_wins' => (int)($user['total_wins'] ?? 0),
        ];

        $_SESSION['flash_success'] = "Đã chuyển đổi sang phiên làm việc của [{$username}] (Vai trò: " . strtoupper($user['role']) . ").";
        return redirect('/admin');
    }

    public function updateRole(int $id)
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!$currentUser || ($currentUser['role'] ?? '') !== 'admin') {
            abort(403, 'Chỉ Quản trị viên (Admin) mới có quyền thay đổi vai trò tài khoản.');
        }

        $newRole = trim($_POST['role'] ?? 'user');
        if (!in_array($newRole, ['admin', 'user'], true)) {
            $_SESSION['flash_error'] = 'Vai trò không hợp lệ! Hệ thống chỉ hỗ trợ vai trò: admin và user (người chơi).';
            return redirect('/admin');
        }

        $db = new \App\Services\PdoDatabaseService();
        $ok = $db->updateUserRole($id, $newRole);
        if ($ok) {
            $_SESSION['flash_success'] = "Đã cập nhật vai trò tài khoản #{$id} thành " . strtoupper($newRole) . " thành công!";
        } else {
            $_SESSION['flash_error'] = "Không thể cập nhật vai trò cho tài khoản #{$id}.";
        }
        return redirect('/admin');
    }

    public function toggleActive(int $id, ?\App\Services\PdoDatabaseService $db = null)
    {
        $currentUser = $_SESSION['huit_auth_user'] ?? null;
        if (!$currentUser || ($currentUser['role'] ?? '') !== 'admin') {
            abort(403, 'Chỉ Quản trị viên (Admin) mới có quyền khóa/mở khóa tài khoản.');
        }

        $db ??= new \App\Services\PdoDatabaseService();
        $res = $db->toggleUserActive($id);
        if ($res['success']) {
            $_SESSION['flash_success'] = $res['message'];
        } else {
            $_SESSION['flash_error'] = $res['message'];
        }
        return redirect('/admin');
    }
}
