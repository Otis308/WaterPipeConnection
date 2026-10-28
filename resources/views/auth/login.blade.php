@extends('layouts.portal')
@section('title', 'Đăng nhập & Đăng ký')

@section('content')
<div class="row g-4 justify-content-center">
    <div class="col-md-5">
        <x-card title="🔑 Đăng nhập Hệ thống" subtitle="Throttle 5 lần/phút">
            <form method="POST" action="{{ route('login.submit') }}">
                @csrf
                <x-input name="username" label="Tên đăng nhập hoặc Email" placeholder="admin / player1" />
                <x-input name="password" type="password" label="Mật khẩu" placeholder="password123" value="password123" />
                <button type="submit" class="btn btn-info fw-bold w-100">Đăng nhập</button>
            </form>
        </x-card>
    </div>

    <div class="col-md-5">
        <x-card title="📝 Đăng ký Tài khoản Người chơi mới">
            <form method="POST" action="{{ route('register.submit') }}">
                @csrf
                <x-input name="username" label="Tên tài khoản (Username)" placeholder="VD: sv_huit_2026" />
                <x-input name="email" type="email" label="Địa chỉ Email" placeholder="sv@huit.edu.vn" />
                <x-input name="password" type="password" label="Mật khẩu" placeholder="Nhập mật khẩu..." />
                <x-input name="password_confirmation" type="password" label="Xác nhận Mật khẩu" placeholder="Nhập lại mật khẩu..." />
                <button type="submit" class="btn btn-success fw-bold w-100">Tạo Tài Khoản Mới</button>
            </form>
        </x-card>
    </div>
</div>
@endsection
