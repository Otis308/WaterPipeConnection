<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin - HUIT Laravel')</title>
    <link rel="stylesheet" href="/Bootstrap/CSS/bootstrap.min.css">
    <script src="/Bootstrap/JS/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/CSS/admin.css">
    @stack('styles')
</head>
<body>
    @include('partials.nav')
    <main class="container py-4">
        @if(!empty($_SESSION['flash_success']))
            <div class="admin-alert admin-alert-success">
                <span class="alert-icon">✓</span>
                <div class="alert-content">
                    <div class="alert-title">Thành công</div>
                    <div class="alert-message">{{ $_SESSION['flash_success'] }}</div>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
            @php unset($_SESSION['flash_success']); @endphp
        @endif
        @if(!empty($_SESSION['flash_error']))
            <div class="admin-alert admin-alert-error">
                <span class="alert-icon">✕</span>
                <div class="alert-content">
                    <div class="alert-title">Lỗi</div>
                    <div class="alert-message">{{ $_SESSION['flash_error'] }}</div>
                </div>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            </div>
            @php unset($_SESSION['flash_error']); @endphp
        @endif

        @yield('content')
    </main>
    @include('partials.footer')

    <!-- Toast container for notifications -->
    <div class="admin-toast-container" id="toastContainer"></div>

    <!-- Admin Dashboard JavaScript -->
    <script src="/JS/admin-dashboard.js"></script>

    @stack('scripts')
</body>
</html>
