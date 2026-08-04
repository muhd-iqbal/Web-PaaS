<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Student Hosting'))</title>
    <style>
        :root { color-scheme: light; --ink:#172033; --muted:#64748b; --line:#dbe3ef; --blue:#2563eb; --blue-dark:#1d4ed8; --surface:#fff; --bg:#f5f7fb; --success:#0f766e; --danger:#b91c1c; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; line-height:1.5; }
        a { color:var(--blue); text-decoration:none; } a:hover { text-decoration:underline; }
        .container { width:min(1120px,calc(100% - 32px)); margin-inline:auto; }
        .nav { background:#0f172a; color:#fff; } .nav-inner { min-height:68px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
        .brand { color:#fff; font-size:1.1rem; font-weight:800; letter-spacing:-.02em; } .brand:hover { text-decoration:none; }
        .nav-links { display:flex; align-items:center; gap:18px; } .nav-links a { color:#dbeafe; } .nav-links form { margin:0; }
        main { padding:48px 0 72px; } h1,h2,h3 { line-height:1.15; letter-spacing:-.025em; } h1 { font-size:clamp(2rem,5vw,3.5rem); margin:0 0 16px; } h2 { margin-top:0; }
        .eyebrow { color:var(--blue); font-size:.78rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .muted { color:var(--muted); } .small { font-size:.875rem; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:0 18px; border:1px solid transparent; border-radius:9px; background:var(--blue); color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .button:hover { background:var(--blue-dark); text-decoration:none; } .button.secondary { background:#fff; border-color:var(--line); color:var(--ink); } .button.danger { background:#fff; border-color:#fecaca; color:var(--danger); }
        .hero { padding:44px 0 24px; max-width:760px; } .hero p { font-size:1.15rem; color:var(--muted); max-width:650px; } .actions { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; } .grid.two { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:14px; padding:24px; box-shadow:0 8px 30px rgba(15,23,42,.04); }
        .price { font-size:2rem; font-weight:800; } .stat { font-size:1.85rem; font-weight:800; margin:.15rem 0; }
        .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; margin-bottom:28px; } .page-head h1 { font-size:2.15rem; }
        table { width:100%; border-collapse:collapse; } th,td { padding:14px 12px; text-align:left; border-bottom:1px solid var(--line); } th { font-size:.78rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
        .badge { display:inline-flex; padding:3px 9px; border-radius:99px; background:#e0e7ff; color:#3730a3; font-size:.78rem; font-weight:700; }
        label { display:block; font-weight:700; margin-bottom:6px; } input,select { width:100%; min-height:44px; padding:9px 11px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:var(--ink); font:inherit; }
        .field { margin-bottom:18px; } .form-card { max-width:620px; margin-inline:auto; } .error { color:var(--danger); font-size:.875rem; margin-top:5px; } .alert { border:1px solid #99f6e4; background:#f0fdfa; color:var(--success); padding:12px 16px; border-radius:9px; margin-bottom:22px; }
        .empty { padding:48px 24px; text-align:center; } ul.clean { list-style:none; padding:0; margin:18px 0 0; } ul.clean li { margin:8px 0; color:var(--muted); }
        .meter { height:9px; overflow:hidden; border-radius:99px; background:#e2e8f0; margin:10px 0 6px; } .meter > span { display:block; height:100%; background:var(--blue); border-radius:inherit; }
        @media (max-width:760px) { .grid,.grid.two { grid-template-columns:1fr; } .nav-inner,.page-head { align-items:flex-start; flex-direction:column; padding:15px 0; } .nav-links { flex-wrap:wrap; } main { padding-top:30px; } .table-wrap { overflow-x:auto; } }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="container nav-inner">
            <a class="brand" href="{{ route('home') }}">Student Hosting</a>
            <div class="nav-links">
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('projects.index') }}">Projects</a>
                    <a href="{{ route('billing.index') }}">Billing</a>
                    @if(auth()->user()->is_admin)<a href="/admin">Admin</a>@endif
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="button secondary" type="submit">Log out</button></form>
                @else
                    <a href="{{ route('login') }}">Log in</a>
                    <a class="button" href="{{ route('register') }}">Get started</a>
                @endauth
            </div>
        </div>
    </nav>
    <main><div class="container">
        @if(session('status'))<div class="alert">{{ session('status') }}</div>@endif
        @yield('content')
    </div></main>
</body>
</html>
