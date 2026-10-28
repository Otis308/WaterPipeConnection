<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hệ thống Quản trị Game Nối Ống Nước — HUIT Laravel 12')</title>
    <link rel="stylesheet" href="/Bootstrap/CSS/bootstrap.min.css">
    <script src="/Bootstrap/JS/bootstrap.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #0b1120;
            color: #e2e8f0;
            font-family: 'Chakra Petch', system-ui, -apple-system, sans-serif;
        }
        .table-dark {
            --bs-table-bg: #111827;
            --bs-table-striped-bg: #1e293b;
        }
        code {
            color: #38bdf8;
        }
    </style>
    @stack('styles')
</head>
<body>
    @include('partials.nav')

    <main class="container py-4">
        @if(!empty($_SESSION['flash_success']))
            <x-alert type="success" title="Thành công">{{ $_SESSION['flash_success'] }}</x-alert>
            @php unset($_SESSION['flash_success']); @endphp
        @endif
        @if(!empty($_SESSION['flash_error']))
            <x-alert type="error" title="Cảnh báo phân quyền / Dữ liệu">{{ $_SESSION['flash_error'] }}</x-alert>
            @php unset($_SESSION['flash_error']); @endphp
        @endif

        @yield('content')
    </main>

    @include('partials.footer')
    @stack('scripts')
</body>
</html>
