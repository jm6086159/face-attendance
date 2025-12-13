<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Face Recognition Attendance System</title>
    @php($faviconVersion = file_exists(public_path('favicon.png')) ? filemtime(public_path('favicon.png')) : time())
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ $faviconVersion }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --hero-image: url('/images/campus.jpg');
            --accent: #1e8449;
            --accent-dark: #13512c;
            --text: #f8fafc;
            --muted: rgba(248, 250, 252, .85);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--text);
            min-height: 100vh;
            background: #061120;
        }
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(115deg, rgba(6,18,49,.9), rgba(8,61,33,.88)), var(--hero-image) center/cover no-repeat;
            filter: brightness(0.9);
            z-index: -2;
        }
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top, rgba(255,255,255,.15), transparent 55%);
            z-index: -1;
        }
        .branding {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: .85rem;
        }
        .crest {
            width: 86px;
            height: 86px;
            display: grid;
            place-items: center;
        }
        .crest img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,.35));
        }
        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.4rem, 5vw, 4.5rem);
            margin: 0.5rem 0;
        }
        .hero .eyebrow {
            text-transform: uppercase;
            letter-spacing: .4em;
            font-size: .85rem;
            color: var(--muted);
        }
        .hero p.lead {
            max-width: 720px;
            margin: 1rem auto 1.8rem;
            color: var(--muted);
            line-height: 1.65;
        }
        .cta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 180px;
            padding: 0.85rem 1.75rem;
            border-radius: 999px;
            border: none;
            font-weight: 600;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #31c46a);
            box-shadow: 0 10px 35px rgba(5, 122, 64, .35);
        }
        .btn.secondary {
            background: transparent;
            border: 1px solid rgba(255,255,255,.4);
            box-shadow: none;
        }
        .stats {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 3rem;
        }
        .stat {
            text-align: center;
        }
        .stat-value {
            font-size: 2.25rem;
            font-weight: 600;
            font-family: 'Playfair Display', serif;
        }
        .stat-label {
            text-transform: uppercase;
            font-size: .8rem;
            letter-spacing: .3em;
            color: var(--muted);
        }
        footer {
            position: absolute;
            bottom: 1.5rem;
            width: 100%;
            text-align: center;
            font-size: 0.85rem;
            color: rgba(248,250,252,.65);
        }
        @media(max-width:600px) {
            .branding { flex-direction: column; gap: .5rem; }
            .crest { margin-bottom: .5rem; }
        }
    </style>
</head>
<body>
<?php
$employeeCount = 0;
$faceCount = 0;
$logCount = 0;
try {
    $employeeCount = \App\Models\Employee::count();
    $faceCount = \App\Models\FaceTemplate::count();
    $logCount = \App\Models\AttendanceLog::count();
} catch (\Throwable $e) {
    // Leave defaults if DB is unavailable.
}
?>

    <section class="hero">
        <header class="branding">
            <div class="crest">
                <img src="/images/logo.png" alt="FRAS Crest">
            </div>
            <div>
                <div style="font-weight:600; letter-spacing:.18em;">ST. FRANCIS XAVIER COLLEGE</div>
                <div style="font-size:.72rem; letter-spacing:.4em;">SAN FRANCISCO • AGUSAN DEL SUR</div>
            </div>
        </header>

        <div class="hero-content">
            <p class="eyebrow">Face Recognition Attendance System</p>
            <h1>Welcome to FRAS</h1>
            <p class="lead">
                Seamlessly register employees, authenticate their presence with face recognition,
                and keep a pristine record of daily time logs — all from one modern dashboard.
            </p>
            <div class="cta">
                @auth
                    <a class="btn" href="{{ route('dashboard') }}">Enter Dashboard</a>
                    <a class="btn secondary" href="{{ route('face.attendance') }}">Open Attendance Camera</a>
                @else
                    <a class="btn" href="{{ route('login') }}">Sign In</a>
                    <a class="btn secondary" href="{{ route('register') }}">Create Administrator Account</a>
                @endauth
            </div>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-value">{{ number_format($employeeCount) }}</div>
                <div class="stat-label">Employees</div>
            </div>
            <div class="stat">
                <div class="stat-value">{{ number_format($faceCount) }}</div>
                <div class="stat-label">Face Templates</div>
            </div>
            <div class="stat">
                <div class="stat-value">{{ number_format($logCount) }}</div>
                <div class="stat-label">Attendance Logs</div>
            </div>
        </div>

        <footer>Face Recognition Attendance System &middot; {{ date('Y') }}</footer>
    </section>
</body>
</html>

