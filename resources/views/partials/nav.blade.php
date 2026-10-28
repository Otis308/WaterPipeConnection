<nav class="navbar navbar-expand-lg navbar-dark border-bottom border-secondary sticky-top" style="background:#0f172a;">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-info d-flex align-items-center gap-2" href="{{ url('/') }}">
            <img src="/Images/CupSao.png" alt="Logo" style="width:28px;height:28px;">
            <span>ADMIN</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            @auth
                <span class="badge bg-info text-dark px-2 py-1">
                    {{ $_SESSION['huit_auth_user']['username'] }} ({{ strtoupper($_SESSION['huit_auth_user']['role']) }})
                </span>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger">Đăng xuất</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login') }}" class="btn btn-sm btn-info fw-bold">Đăng nhập</a>
            @endguest
        </div>
    </div>
</nav>
