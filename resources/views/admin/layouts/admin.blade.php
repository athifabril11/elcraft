<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Atelier & Aksesoris — el Craft">
    <title>@yield('title', 'Admin') — el Craft</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Material Symbols for icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#FDF8F7',
                            100: '#FAF0EE',
                            200: '#F4DDD8',
                            300: '#E8B4AC',
                            400: '#D58E82',
                            500: '#C17B6F', /* Signature Rose Gold */
                            600: '#A8584B',
                            700: '#8E4034',
                            800: '#753229',
                            900: '#612A23',
                            DEFAULT: '#C17B6F',
                        },
                        atelier: {
                            cream: '#FAF7F5',
                            card: '#FFFFFF',
                            border: '#EFE7E4',
                            borderSubtle: '#F6EFEB',
                            textDark: '#221F1E',
                            textMuted: '#786F6B',
                            textLight: '#A39994',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            letter-spacing: -0.01em;
        }

        /* Custom subtle scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #FAF7F5;
        }
        ::-webkit-scrollbar-thumb {
            background: #E2D5D0;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #C17B6F;
        }

        /* Backward-compatible utility classes */
        .admin-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #EDE8E4;
            box-shadow: 0 1px 4px rgba(0,0,0,0.03);
        }

        .admin-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #C17B6F;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 1.1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .admin-btn-primary:hover {
            background: #9E5A4E;
            color: #fff;
        }

        .admin-btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: #78716C;
            border: 1px solid #EDE8E4;
            border-radius: 10px;
            padding: 0.5rem 1.1rem;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .admin-btn-ghost:hover {
            background: #FDF8F6;
            border-color: #C17B6F;
            color: #C17B6F;
        }

        .admin-btn-danger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fef2f2;
            color: #ef4444;
            border: 1px solid #fee2e2;
            border-radius: 10px;
            padding: 0.45rem 0.95rem;
            font-size: 0.775rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .admin-btn-danger:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .admin-input {
            width: 100%;
            padding: 0.55rem 0.85rem;
            border: 1px solid #EDE8E4;
            border-radius: 10px;
            font-size: 0.8125rem;
            color: #1c1917;
            background: #fff;
            outline: none;
            transition: all 0.15s ease;
        }
        .admin-input:focus {
            border-color: #C17B6F;
            box-shadow: 0 0 0 3px rgba(193, 123, 111, 0.15);
        }

        .admin-label {
            display: block;
            font-size: 0.725rem;
            font-weight: 700;
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
<body class="bg-[#F8F5F2] text-atelier-textDark antialiased h-screen overflow-hidden selection:bg-brand-100 selection:text-brand-800">

    <div class="flex h-screen overflow-hidden">

        @php
            $pendingOrdersCount = \App\Models\Order::whereIn('status', ['paid', 'diproses'])->count();
            $pendingReturnsCount = \App\Models\ReturnRequest::where('status', 'diajukan')->count();
        @endphp

        <!-- ========================================================= -->
        <!-- CLEAN ATELIER SIDEBAR WITH ROSE GOLD ACCENT -->
        <!-- ========================================================= -->
        <aside class="w-64 bg-white border-r border-atelier-border flex flex-col shrink-0 h-screen relative z-20 shadow-[1px_0_12px_rgba(0,0,0,0.02)]">

            <!-- Brand Header -->
            <div class="p-6 border-b border-atelier-border/80 flex items-center justify-between shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group text-decoration-none">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-100 via-white to-brand-50 border border-brand-200 flex items-center justify-center shadow-xs group-hover:border-brand-400 transition-colors">
                        <!-- Elegant Jewelry Diamond Vector Icon -->
                        <svg class="w-5 h-5 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="6 3 18 3 22 9 12 22 2 9 6 3"></polygon>
                            <line x1="12" y1="22" x2="12" y2="9"></line>
                            <line x1="2" y1="9" x2="22" y2="9"></line>
                        </svg>
                    </div>
                    <div>
                        <div class="font-extrabold text-xl text-atelier-textDark tracking-tight leading-none group-hover:text-brand-600 transition-colors font-sans">el Craft</div>
                        <div class="text-[9px] font-bold text-brand-600 uppercase tracking-[0.16em] mt-1.5 font-sans">Admin Atelier & Aksesoris</div>
                    </div>
                </a>
            </div>

            <!-- Navigation Links -->
            <nav class="flex-1 px-4 py-5 space-y-6 overflow-y-auto">

                <!-- Kategori 1: UTAMA -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-atelier-textLight mb-2.5">Utama</div>
                    <div class="space-y-1">
                        <!-- Dashboard -->
                        <a href="{{ route('admin.dashboard') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all relative group {{ request()->routeIs('admin.dashboard') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 shadow-[0_1px_3px_rgba(193,123,111,0.08)]' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.dashboard'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.dashboard') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                                <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                                <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                                <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                            </svg>
                            <span class="flex-1">Dashboard</span>
                        </a>

                        <!-- Pesanan -->
                        <a href="{{ route('admin.orders.index') }}"
                           class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.orders.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.orders.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 {{ request()->routeIs('admin.orders.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                                </svg>
                                <span>Pesanan</span>
                            </div>
                            @if($pendingOrdersCount > 0)
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-brand-100 text-brand-700 rounded-full border border-brand-200">
                                    {{ $pendingOrdersCount }}
                                </span>
                            @endif
                        </a>

                        <!-- Retur & Refund -->
                        <a href="{{ route('admin.returns.index') }}"
                           class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.returns.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.returns.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 {{ request()->routeIs('admin.returns.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                    <path d="M21 3v5h-5"></path>
                                    <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                    <path d="M8 16H3v5"></path>
                                </svg>
                                <span>Retur & Refund</span>
                            </div>
                            @if($pendingReturnsCount > 0)
                                <span class="px-2 py-0.5 text-[10px] font-bold bg-rose-100 text-rose-700 rounded-full border border-rose-200 animate-pulse">
                                    {{ $pendingReturnsCount }}
                                </span>
                            @endif
                        </a>

                        <!-- Laporan & Analitik -->
                        <a href="{{ route('admin.reports.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.reports.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.reports.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.reports.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <line x1="18" y1="20" x2="18" y2="10"></line>
                                <line x1="12" y1="20" x2="12" y2="4"></line>
                                <line x1="6" y1="20" x2="6" y2="14"></line>
                            </svg>
                            <span>Laporan & Analitik</span>
                        </a>

                        <!-- Pelanggan -->
                        <a href="{{ route('admin.customers.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.customers.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.customers.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.customers.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                            <span>Pelanggan</span>
                        </a>
                    </div>
                </div>

                <!-- Kategori 2: PEMASARAN -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-atelier-textLight mb-2.5">Pemasaran</div>
                    <div class="space-y-1">
                        <!-- Voucher Diskon -->
                        <a href="{{ route('admin.vouchers.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.vouchers.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.vouchers.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.vouchers.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M2.5 12a2.5 2.5 0 0 1 0-5h19a2.5 2.5 0 0 1 0 5 2.5 2.5 0 0 1 0 5h-19a2.5 2.5 0 0 1 0-5z"></path>
                                <path d="M9 7v10" stroke-dasharray="2 2"></path>
                            </svg>
                            <span>Voucher Diskon</span>
                        </a>

                        <!-- Banner Promo -->
                        <a href="{{ route('admin.banners.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.banners.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.banners.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.banners.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <circle cx="8" cy="10" r="2"></circle>
                                <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 20"></path>
                            </svg>
                            <span>Banner Promo</span>
                        </a>
                    </div>
                </div>

                <!-- Kategori 3: PENGATURAN TOKO -->
                <div>
                    <div class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-atelier-textLight mb-2.5">Pengaturan Toko</div>
                    <div class="space-y-1">
                        <!-- Kota Asal Kirim -->
                        <a href="{{ route('admin.settings.shipping.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.settings.shipping.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.settings.shipping.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.settings.shipping.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            <span>Kota Asal Kirim</span>
                        </a>

                        <!-- Diskon Ongkir -->
                        <a href="{{ route('admin.settings.shipping-discounts.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.settings.shipping-discounts.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.settings.shipping-discounts.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.settings.shipping-discounts.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <line x1="19" y1="5" x2="5" y2="19"></line>
                                <circle cx="6.5" cy="6.5" r="2.5"></circle>
                                <circle cx="17.5" cy="17.5" r="2.5"></circle>
                            </svg>
                            <span>Diskon Ongkir</span>
                        </a>

                        <!-- Kurir Ekspedisi -->
                        <a href="{{ route('admin.settings.couriers.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.settings.couriers.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.settings.couriers.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.settings.couriers.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="1" y="3" width="15" height="13" rx="1"></rect>
                                <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                                <circle cx="5.5" cy="18.5" r="2.5"></circle>
                                <circle cx="18.5" cy="18.5" r="2.5"></circle>
                            </svg>
                            <span>Kurir Ekspedisi</span>
                        </a>

                        <!-- Auto-Selesai Pesanan -->
                        <a href="{{ route('admin.settings.order-auto-complete.index') }}"
                           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-medium transition-all group {{ request()->routeIs('admin.settings.order-auto-complete.*') ? 'text-brand-700 bg-brand-50/80 border border-brand-200/90 font-bold relative shadow-xs' : 'text-atelier-textMuted hover:text-brand-700 hover:bg-brand-50/40 border border-transparent' }}">
                            @if(request()->routeIs('admin.settings.order-auto-complete.*'))
                                <span class="absolute left-0 top-2 bottom-2 w-1 bg-brand-500 rounded-r-full"></span>
                            @endif
                            <svg class="w-4 h-4 {{ request()->routeIs('admin.settings.order-auto-complete.*') ? 'text-brand-600' : 'text-atelier-textLight group-hover:text-brand-500' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span>Auto-Selesai</span>
                        </a>
                    </div>
                </div>

            </nav>

            <!-- Bottom Action & Profile -->
            <div class="p-4 border-t border-atelier-border bg-gradient-to-b from-white to-brand-50/30 space-y-3 shrink-0">
                <!-- Lihat Toko Action -->
                <a href="{{ route('home') }}"
                   target="_blank"
                   class="flex items-center justify-center gap-2 py-2 px-3 rounded-xl text-xs font-bold text-brand-700 bg-white border border-brand-200 hover:bg-brand-50 hover:border-brand-400 transition-all shadow-xs">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span>Lihat Toko Publik</span>
                </a>

                <!-- Profile Card -->
                <div class="p-2.5 rounded-xl border border-brand-100 bg-white/95 flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-brand-400 to-brand-600 text-white flex items-center justify-center text-xs font-bold shadow-xs shrink-0">
                            {{ strtoupper(substr(auth()->user()->name ?? 'AD', 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-atelier-textDark truncate">{{ auth()->user()->name ?? 'Administrator' }}</div>
                            <div class="text-[10px] text-brand-600 font-medium truncate">Toko el Craft</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" title="Keluar / Logout" class="text-atelier-textLight hover:text-rose-600 p-1 rounded-md hover:bg-rose-50 transition border-0 bg-transparent cursor-pointer">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- ========================================================= -->
        <!-- MAIN CONTENT AREA -->
        <!-- ========================================================= -->
        <div class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">

            <!-- Top Global Header Bar -->
            <header class="bg-white border-b border-atelier-border px-6 py-3 flex items-center justify-between gap-4 sticky top-0 z-10 shrink-0">

                <!-- Left: Global Live Search Bar -->
                <div class="flex-1 max-w-lg relative" id="global-search-container">
                    <form method="GET" action="{{ route('admin.orders.index') }}" id="global-search-form" class="relative flex items-center">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-atelier-textLight">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                        <input type="text" name="q" id="global-search-input"
                               value="{{ request('q') }}"
                               autocomplete="off"
                               placeholder="Cari pesanan, produk, pelanggan, menu... (Ctrl+K)"
                               class="w-full pl-10 pr-20 py-2 text-xs bg-stone-50 hover:bg-white focus:bg-white border border-stone-200 focus:border-brand-400 rounded-xl outline-none transition-all placeholder:text-stone-400">

                        <!-- Spinner Indicator -->
                        <span id="global-search-spinner" class="hidden absolute right-10 flex items-center pointer-events-none text-brand-500">
                            <svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </span>

                        <!-- Clear button -->
                        <button type="button" id="global-search-clear" class="hidden absolute right-10 text-stone-400 hover:text-stone-600 p-0.5 rounded cursor-pointer">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>

                        <!-- Keyboard Shortcut badge -->
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <kbd class="text-[10px] font-mono text-stone-400 bg-stone-100 border border-stone-200 px-1.5 py-0.5 rounded shadow-2xs font-semibold">Ctrl K</kbd>
                        </span>
                    </form>

                    <!-- Live Search Dropdown Panel -->
                    <div id="global-search-dropdown" class="hidden absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl border border-atelier-border shadow-2xl z-50 overflow-hidden max-h-[70vh] flex flex-col">
                        <div id="global-search-results-content" class="overflow-y-auto divide-y divide-atelier-border p-1 text-xs">
                            <!-- Injected by JavaScript -->
                        </div>
                        <div class="px-4 py-2 bg-stone-50 border-t border-atelier-border text-[11px] text-atelier-textLight flex items-center justify-between">
                            <span>Tekan <strong class="text-stone-700 font-semibold">Enter</strong> untuk cari di Pesanan</span>
                            <span>Tekan <strong class="text-stone-700 font-semibold">Esc</strong> untuk tutup</span>
                        </div>
                    </div>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-3">

                    <!-- Date & Store Status -->
                    <div class="flex items-center gap-2.5 text-xs font-medium text-atelier-textMuted border-r border-atelier-border pr-3">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-atelier-textLight" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span class="font-mono text-[11px]">
                                {{ \Carbon\Carbon::now('Asia/Jakarta')->format('H:i') }} WIB • {{ \Carbon\Carbon::now('Asia/Jakarta')->translatedFormat('d M') }}
                            </span>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Toko Buka
                        </span>
                    </div>

                    <!-- Notification Bell -->
                    @php
                        $notifCount = ($pendingOrdersCount ?? 0) + ($pendingReturnsCount ?? 0);
                    @endphp
                    <button id="notif-bell-btn" title="Notifikasi" class="relative p-2 rounded-xl text-atelier-textMuted hover:text-brand-600 hover:bg-brand-50/60 border border-transparent hover:border-brand-200 transition">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        @if($notifCount > 0)
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-brand-500 ring-2 ring-white"></span>
                        @endif
                    </button>

                    <!-- Notification Dropdown -->
                    <div id="notif-panel" class="hidden absolute top-14 right-6 w-80 bg-white rounded-2xl border border-atelier-border shadow-xl z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-atelier-border flex items-center justify-between">
                            <span class="text-xs font-bold text-atelier-textDark">Notifikasi</span>
                            @if($notifCount > 0)
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-100 text-brand-700 border border-brand-200">{{ $notifCount }} baru</span>
                            @endif
                        </div>
                        <div class="divide-y divide-atelier-border max-h-64 overflow-y-auto">
                            @if(($pendingOrdersCount ?? 0) > 0)
                                <a href="{{ route('admin.orders.index') }}" class="flex items-start gap-3 px-4 py-3 hover:bg-brand-50/40 transition">
                                    <span class="w-8 h-8 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                    </span>
                                    <div>
                                        <div class="text-xs font-bold text-atelier-textDark">{{ $pendingOrdersCount }} Pesanan Perlu Diproses</div>
                                        <div class="text-[11px] text-atelier-textMuted mt-0.5">Pesanan baru menunggu konfirmasi & packing</div>
                                    </div>
                                </a>
                            @endif
                            @if(($pendingReturnsCount ?? 0) > 0)
                                <a href="{{ route('admin.returns.index', ['status' => 'diajukan']) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-brand-50/40 transition">
                                    <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path><path d="M8 16H3v5"></path></svg>
                                    </span>
                                    <div>
                                        <div class="text-xs font-bold text-atelier-textDark">{{ $pendingReturnsCount }} Retur Menunggu Keputusan</div>
                                        <div class="text-[11px] text-atelier-textMuted mt-0.5">Pengajuan retur butuh review admin</div>
                                    </div>
                                </a>
                            @endif
                            @if($notifCount === 0)
                                <div class="px-4 py-6 text-center text-xs text-atelier-textMuted">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-stone-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                    Tidak ada notifikasi baru
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- User Avatar -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-stone-800 hidden sm:block">{{ auth()->user()->name ?? 'Admin' }}</span>
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-brand-200 via-brand-100 to-white border border-brand-300 flex items-center justify-center text-brand-700 font-bold text-xs shadow-xs cursor-pointer">
                            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            @if(session('success'))
                <div class="mx-8 mt-6 bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-center gap-3 text-xs text-emerald-800 shadow-2xs">
                    <span class="material-symbols-outlined text-emerald-600" style="font-size:20px;">check_circle</span>
                    <span class="font-medium flex-1">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mx-8 mt-6 bg-rose-50 border border-rose-200 rounded-2xl p-4 flex items-center gap-3 text-xs text-rose-800 shadow-2xs">
                    <span class="material-symbols-outlined text-rose-600" style="font-size:20px;">error</span>
                    <span class="font-medium flex-1">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Page Body -->
            <main class="flex-1 p-8 space-y-6 overflow-y-auto">
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')

    <script>
        // Notification bell toggle
        const bellBtn = document.getElementById('notif-bell-btn');
        const notifPanel = document.getElementById('notif-panel');
        if (bellBtn && notifPanel) {
            bellBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifPanel.classList.toggle('hidden');
            });
            document.addEventListener('click', (e) => {
                if (!notifPanel.contains(e.target) && e.target !== bellBtn) {
                    notifPanel.classList.add('hidden');
                }
            });
        }

        // ==========================================
        // GLOBAL LIVE SEARCH SCRIPT
        // ==========================================
        const searchInput = document.getElementById('global-search-input');
        const searchDropdown = document.getElementById('global-search-dropdown');
        const searchResultsContent = document.getElementById('global-search-results-content');
        const searchSpinner = document.getElementById('global-search-spinner');
        const searchClear = document.getElementById('global-search-clear');
        const searchContainer = document.getElementById('global-search-container');
        const searchUrl = "{{ route('admin.global-search') }}";

        let searchDebounceTimer = null;

        // Shortcut: Ctrl+K or Cmd+K
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            } else if (e.key === 'Escape') {
                if (searchDropdown) {
                    searchDropdown.classList.add('hidden');
                }
            }
        });

        if (searchInput && searchDropdown && searchResultsContent) {
            // Input event with debounce
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();

                if (searchClear) {
                    if (query.length > 0) {
                        searchClear.classList.remove('hidden');
                    } else {
                        searchClear.classList.add('hidden');
                    }
                }

                if (query.length < 2) {
                    searchDropdown.classList.add('hidden');
                    searchResultsContent.innerHTML = '';
                    if (searchSpinner) searchSpinner.classList.add('hidden');
                    return;
                }

                if (searchSpinner) searchSpinner.classList.remove('hidden');
                clearTimeout(searchDebounceTimer);

                searchDebounceTimer = setTimeout(() => {
                    fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (searchSpinner) searchSpinner.classList.add('hidden');
                        renderSearchResults(data, query);
                    })
                    .catch(err => {
                        if (searchSpinner) searchSpinner.classList.add('hidden');
                        console.error('Global search error:', err);
                    });
                }, 250);
            });

            // Focus opens dropdown if query already entered
            searchInput.addEventListener('focus', () => {
                if (searchInput.value.trim().length >= 2 && searchResultsContent.children.length > 0) {
                    searchDropdown.classList.remove('hidden');
                }
            });

            // Clear button
            if (searchClear) {
                searchClear.addEventListener('click', () => {
                    searchInput.value = '';
                    searchClear.classList.add('hidden');
                    searchDropdown.classList.add('hidden');
                    searchResultsContent.innerHTML = '';
                    searchInput.focus();
                });
            }

            // Click outside closes
            document.addEventListener('click', (e) => {
                if (searchContainer && !searchContainer.contains(e.target)) {
                    searchDropdown.classList.add('hidden');
                }
            });
        }

        function renderSearchResults(data, query) {
            const results = data.results || {};
            const total = data.total || 0;

            if (total === 0) {
                searchResultsContent.innerHTML = `
                    <div class="py-8 px-4 text-center">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-stone-100 flex items-center justify-center text-stone-400">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </div>
                        <div class="text-xs font-bold text-stone-700">Tidak ada hasil untuk "${escapeHtml(query)}"</div>
                        <div class="text-[11px] text-stone-400 mt-0.5">Coba cari dengan nama produk, nomor pesanan, atau nama pelanggan.</div>
                    </div>
                `;
                searchDropdown.classList.remove('hidden');
                return;
            }

            let html = '';

            // 1. Menus
            if (results.menus && results.menus.length > 0) {
                html += renderSearchSection('Navigasi Menu', results.menus, 'navigation');
            }

            // 2. Orders
            if (results.orders && results.orders.length > 0) {
                html += renderSearchSection('Pesanan', results.orders, 'order');
            }

            // 3. Products
            if (results.products && results.products.length > 0) {
                html += renderSearchSection('Produk Toko', results.products, 'product');
            }

            // 4. Customers
            if (results.customers && results.customers.length > 0) {
                html += renderSearchSection('Pelanggan', results.customers, 'customer');
            }

            // 5. Returns
            if (results.returns && results.returns.length > 0) {
                html += renderSearchSection('Retur & Refund', results.returns, 'return');
            }

            searchResultsContent.innerHTML = html;
            searchDropdown.classList.remove('hidden');
        }

        function renderSearchSection(sectionTitle, items, type) {
            let sectionHtml = `<div class="py-2">`;
            sectionHtml += `<div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-atelier-textLight">${sectionTitle}</div>`;
            sectionHtml += `<div class="mt-1 space-y-0.5">`;

            items.forEach(item => {
                let badgeClass = 'bg-stone-100 text-stone-700';
                if (item.badge_color === 'brand') badgeClass = 'bg-brand-50 text-brand-700 border border-brand-200';
                if (item.badge_color === 'emerald') badgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                if (item.badge_color === 'indigo') badgeClass = 'bg-indigo-50 text-indigo-700 border border-indigo-200';
                if (item.badge_color === 'purple') badgeClass = 'bg-purple-50 text-purple-700 border border-purple-200';
                if (item.badge_color === 'rose') badgeClass = 'bg-rose-50 text-rose-700 border border-rose-200';
                if (item.badge_color === 'amber') badgeClass = 'bg-amber-50 text-amber-700 border border-amber-200';

                sectionHtml += `
                    <a href="${item.url}" class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl hover:bg-brand-50/50 group transition">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-6 h-6 rounded-lg bg-stone-100 group-hover:bg-white text-stone-500 group-hover:text-brand-600 flex items-center justify-center shrink-0 border border-stone-200/60 transition">
                                ${getTypeIcon(type)}
                            </span>
                            <div class="min-w-0">
                                <div class="font-bold text-atelier-textDark group-hover:text-brand-700 truncate transition text-xs">${escapeHtml(item.title)}</div>
                                <div class="text-[10px] text-atelier-textMuted truncate">${escapeHtml(item.subtitle)}</div>
                            </div>
                        </div>
                        ${item.badge ? `<span class="px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0 ${badgeClass}">${escapeHtml(item.badge)}</span>` : ''}
                    </a>
                `;
            });

            sectionHtml += `</div></div>`;
            return sectionHtml;
        }

        function getTypeIcon(type) {
            if (type === 'navigation') return '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>';
            if (type === 'order') return '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
            if (type === 'product') return '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="6 3 18 3 22 9 12 22 2 9 6 3"></polygon></svg>';
            if (type === 'customer') return '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
            if (type === 'return') return '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path></svg>';
            return '<svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle></svg>';
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
