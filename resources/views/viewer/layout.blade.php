<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @stack('head')
    <title>@yield('title') · CHIS Simulator</title>
    {{-- Self-contained styles: no CDN, so the demo works offline. --}}
    <style>
        :root { --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; --bg:#f3f4f6; --brand:#0f5132; --brand-soft:#e7f3ec; --warn:#92400e; --warn-soft:#fef3c7; --bad:#991b1b; --bad-soft:#fee2e2; }
        * { box-sizing: border-box; }
        body { margin:0; font:15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color:var(--ink); background:var(--bg); }
        .banner { background:var(--warn-soft); color:var(--warn); text-align:center; font-weight:600; padding:6px 12px; font-size:13px; }
        header { background:var(--brand); color:#fff; padding:14px 24px; display:flex; align-items:center; gap:24px; flex-wrap:wrap; }
        header h1 { margin:0; font-size:20px; }
        header nav a { color:#d1fae5; text-decoration:none; margin-right:18px; font-weight:600; }
        header nav a.active { color:#fff; border-bottom:2px solid #fff; padding-bottom:2px; }
        main { max-width:1150px; margin:24px auto; padding:0 16px; }
        .card { background:#fff; border:1px solid var(--line); border-radius:10px; padding:18px 20px; margin-bottom:18px; }
        h2 { margin:0 0 6px; font-size:18px; } h3 { margin:0 0 8px; font-size:15px; color:var(--brand); }
        .muted { color:var(--muted); font-size:13px; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:9px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
        tr.new td { background:var(--brand-soft); }
        a { color:var(--brand); }
        .badge { display:inline-block; padding:1px 8px; border-radius:999px; font-size:12px; font-weight:700; background:var(--line); }
        .badge.new { background:var(--brand); color:#fff; } .badge.severe { background:var(--bad-soft); color:var(--bad); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:14px; }
        dl { margin:0; display:grid; grid-template-columns:max-content 1fr; gap:4px 14px; } dt { color:var(--muted); } dd { margin:0; }
        ul.plain { margin:0; padding-left:18px; }
        .btn { display:inline-block; background:var(--brand); color:#fff; border:0; border-radius:8px; padding:8px 14px; font-weight:600; text-decoration:none; cursor:pointer; font-size:14px; }
        .btn.secondary { background:#fff; color:var(--brand); border:1px solid var(--brand); }
        label { display:block; font-weight:600; font-size:13px; margin-bottom:4px; }
        input, select, textarea { width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; }
        textarea { font-family: ui-monospace, Consolas, monospace; font-size:13px; min-height:84px; }
        .field { margin-bottom:12px; } .hint { color:var(--muted); font-size:12px; margin-top:3px; }
        .flash { background:var(--brand-soft); border:1px solid #a7d7bb; color:var(--brand); padding:10px 14px; border-radius:8px; margin-bottom:16px; }
        .errors { background:var(--bad-soft); border:1px solid #fca5a5; color:var(--bad); padding:10px 14px; border-radius:8px; margin-bottom:16px; }
        pre { background:#0f172a; color:#e2e8f0; padding:14px; border-radius:8px; overflow:auto; font-size:12px; }
        .row { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
    </style>
</head>
<body>
    <div class="banner">CHIS Simulator — a stand-in built for the capstone demo. This is not CLSU's real Comprehensive Health Information System.</div>
    <header>
        <h1>CHIS</h1>
        <nav>
            <a href="{{ route('viewer.encounters.index') }}" class="{{ request()->routeIs('viewer.encounters.*') ? 'active' : '' }}">Received encounters</a>
            <a href="{{ route('viewer.patients.index') }}" class="{{ request()->routeIs('viewer.patients.*') ? 'active' : '' }}">Patient records</a>
        </nav>
    </header>
    <main>
        @if (session('status'))
            <div class="flash" role="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
