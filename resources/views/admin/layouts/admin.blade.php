<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Panel — el Craft">
    <title>@yield('title', 'Admin') — el Craft Admin</title>

    <!-- Inter font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Material Symbols for icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Admin-specific overrides */
        body { font-family: 'Inter', sans-serif; }

        .admin-sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.6rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #78716C;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .admin-sidebar-link:hover {
            background: #FDF8F6;
            color: #C17B6F;
        }
        .admin-sidebar-link.active {
            background: #C17B6F18;
            color: #C17B6F;
            font-weight: 600;
        }
        .admin-sidebar-link .material-symbols-outlined {
            font-size: 18px;
        }

        .admin-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #EDE8E4;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .admin-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #C17B6F;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1.1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .admin-btn-primary:hover { background: #9E5A4E; }

        .admin-btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            color: #78716C;
            border: 1px solid #EDE8E4;
            border-radius: 8px;
            padding: 0.5rem 1.1rem;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
        }
        .admin-btn-ghost:hover { background: #FDF8F6; border-color: #C17B6F; color: #C17B6F; }

        .admin-btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            color: #ef4444;
            border: 1px solid #fee2e2;
            border-radius: 8px;
            padding: 0.4rem 0.9rem;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
        }
        .admin-btn-danger:hover { background: #fee2e2; }

        .admin-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #EDE8E4;
            border-radius: 8px;
            font-size: 0.8125rem;
            color: #1c1917;
            background: #fff;
            outline: none;
            transition: border-color 0.15s;
        }
        .admin-input:focus { border-color: #C17B6F; box-shadow: 0 0 0 3px #C17B6F18; }

        .admin-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #78716C;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .badge-active   { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-inactive { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-free     { background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-fixed    { background: #ede9fe; color: #5b21b6; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-province { background: #fce7f3; color: #9d174d; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-city     { background: #ecfdf5; color: #065f46; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
    </style>
</head>
<body class="antialiased" style="background:#F9F6F4; min-height:100vh;">

    <div style="display:flex; min-height:100vh;">

        <!-- Sidebar -->
        <aside style="width:220px; min-width:220px; background:#fff; border-right:1px solid #EDE8E4; display:flex; flex-direction:column; padding:1.5rem 1rem;">
            <!-- Brand -->
            <a href="{{ route('home') }}" style="display:block; margin-bottom:2rem; text-decoration:none;">
                <div style="font-size:1.1rem; font-weight:700; color:#C17B6F; letter-spacing:-0.02em;">el Craft</div>
                <div style="font-size:0.65rem; color:#78716C; font-weight:500; text-transform:uppercase; letter-spacing:0.08em;">Admin Panel</div>
            </a>

            <!-- Navigation -->
            <nav style="flex:1; display:flex; flex-direction:column; gap:2px;">
                <div style="font-size:0.65rem; font-weight:700; color:#b5b0ad; text-transform:uppercase; letter-spacing:0.1em; padding:0 0.5rem; margin-bottom:4px;">Dashboard</div>
                <a href="{{ route('admin.dashboard') }}"
                   class="admin-sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">grid_view</span>
                    Dashboard
                </a>

                <div style="font-size:0.65rem; font-weight:700; color:#b5b0ad; text-transform:uppercase; letter-spacing:0.1em; padding:0 0.5rem; margin:12px 0 4px;">Pengaturan Toko</div>
                <a href="{{ route('admin.settings.shipping.index') }}"
                   class="admin-sidebar-link {{ request()->routeIs('admin.settings.shipping.*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">warehouse</span>
                    Kota Asal
                </a>
                <a href="{{ route('admin.settings.shipping-discounts.index') }}"
                   class="admin-sidebar-link {{ request()->routeIs('admin.settings.shipping-discounts.*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">local_offer</span>
                    Diskon Ongkir
                </a>
                <a href="{{ route('admin.settings.couriers.index') }}"
                   class="admin-sidebar-link {{ request()->routeIs('admin.settings.couriers.*') ? 'active' : '' }}">
                    <span class="material-symbols-outlined">local_shipping</span>
                    Kurir
                </a>
            </nav>

            <!-- Footer -->
            <div style="border-top:1px solid #EDE8E4; padding-top:1rem; margin-top:1rem;">
                <a href="{{ route('home') }}" class="admin-sidebar-link">
                    <span class="material-symbols-outlined">storefront</span>
                    Lihat Toko
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="admin-sidebar-link" style="width:100%; background:none; border:none; cursor:pointer; text-align:left;">
                        <span class="material-symbols-outlined">logout</span>
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
            <!-- Top bar -->
            <header style="background:#fff; border-bottom:1px solid #EDE8E4; padding:0.875rem 2rem; display:flex; align-items:center; justify-content:space-between; min-height:60px;">
                <div>
                    <h1 style="font-size:1rem; font-weight:600; color:#1c1917; margin:0;">@yield('page-title', 'Dashboard')</h1>
                    @hasSection('page-subtitle')
                        <p style="font-size:0.75rem; color:#78716C; margin:2px 0 0;">@yield('page-subtitle')</p>
                    @endif
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:0.75rem; color:#78716C;">{{ auth()->user()->name }}</span>
                    <div style="width:32px; height:32px; border-radius:50%; background:#C17B6F22; display:flex; align-items:center; justify-content:center;">
                        <span class="material-symbols-outlined" style="font-size:18px; color:#C17B6F;">person</span>
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            @if(session('success'))
                <div style="background:#d1fae5; border-left:4px solid #10b981; padding:0.75rem 2rem; font-size:0.8125rem; color:#065f46; display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined" style="font-size:18px;">check_circle</span>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div style="background:#fee2e2; border-left:4px solid #ef4444; padding:0.75rem 2rem; font-size:0.8125rem; color:#991b1b; display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined" style="font-size:18px;">error</span>
                    {{ session('error') }}
                </div>
            @endif

            <!-- Page body -->
            <main style="flex:1; padding:2rem; overflow-y:auto;">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
