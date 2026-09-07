<!DOCTYPE html>
<html class="scroll-smooth" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>@yield('title', 'el Craft | Timeless Elegance & Premium Accessories')</title>

    {{-- SEO Meta Tags --}}
    <meta name="description" content="@yield('meta_description', 'el Craft menyediakan koleksi aksesoris wanita premium — gelang, kalung, cincin, anting, dan bros berkualitas tinggi dengan desain elegan dan harga terjangkau.')"/>
    <meta name="keywords" content="@yield('meta_keywords', 'aksesoris wanita, gelang, kalung, cincin, anting, bros, perhiasan, el Craft, aksesoris premium, hadiah wanita')"/>
    <meta name="robots" content="index, follow"/>
    <link rel="canonical" href="{{ url()->current() }}"/>

    {{-- Open Graph Meta Tags --}}
    <meta property="og:title" content="@yield('title', 'el Craft | Timeless Elegance & Premium Accessories')"/>
    <meta property="og:description" content="@yield('meta_description', 'el Craft menyediakan koleksi aksesoris wanita premium — gelang, kalung, cincin, anting, dan bros berkualitas tinggi dengan desain elegan dan harga terjangkau.')"/>
    <meta property="og:type" content="@yield('og_type', 'website')"/>
    <meta property="og:url" content="{{ url()->current() }}"/>
    <meta property="og:site_name" content="el Craft"/>
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')"/>
    @endif

    <!-- Self-hosted fonts: Inter + Material Symbols Outlined -->
    <!-- Tidak bergantung pada koneksi eksternal ke Google Fonts CDN -->
    <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/material-symbols-outlined.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/fonts/fonts.css">

    <!-- CSS & JS dikompilasi oleh Vite — token desain dari tailwind.config.js -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>[x-cloak] { display: none !important; }</style>

    <script>
        window.isUserLoggedIn = {{ auth()->check() ? 'true' : 'false' }};
        window.isAdminUser = {{ (auth()->check() && auth()->user()->role === 'admin') ? 'true' : 'false' }};
        const isUserLoggedIn = window.isUserLoggedIn;
        const isAdminUser = window.isAdminUser;

        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = `transform translate-x-12 opacity-0 transition-all duration-300 pointer-events-auto bg-white border border-warmLightGrey shadow-xs rounded-[6px] p-4 flex items-center space-x-3 min-w-[280px] max-w-sm`;
            
            const iconColor = type === 'success' ? 'text-brand' : 'text-warmGrey';
            const iconName = type === 'success' ? 'check_circle' : 'info';
            
            toast.innerHTML = `
                <span class="material-symbols-outlined ${iconColor}"> ${iconName} </span>
                <div class="flex-1 text-xs font-semibold text-warmBlack">${message}</div>
                <button onclick="this.parentElement.remove()" class="text-warmGrey hover:text-warmBlack flex items-center justify-center p-0.5">
                    <span class="material-symbols-outlined !text-[16px]">close</span>
                </button>
            `;
            
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('translate-x-12', 'opacity-0');
            }, 10);
            
            setTimeout(() => {
                toast.classList.add('translate-x-12', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }
        window.showToast = showToast;

        window.openLoginModal = () => {
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data) {
                    data.loginModalOpen = true;
                    return;
                }
            }
            const openAfterReady = () => {
                if (window.Alpine) {
                    const data = Alpine.$data(document.body);
                    if (data) data.loginModalOpen = true;
                }
            };
            document.addEventListener('DOMContentLoaded', openAfterReady, { once: true });
            document.addEventListener('alpine:initialized', openAfterReady, { once: true });
            setTimeout(openAfterReady, 100);
        };

        // Buka modal login otomatis jika middleware RedirectGuestsWithModal mem-flash kunci ini
        @if(session('show_login_modal'))
        document.addEventListener('alpine:initialized', () => {
            window.openLoginModal();
            const data = Alpine.$data(document.body);
            if (data) data.authTab = @json(session('auth_modal_tab', 'login'));
        }, { once: true });
        setTimeout(() => {
            window.openLoginModal();
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data) data.authTab = @json(session('auth_modal_tab', 'login'));
            }
        }, 200);
        @endif

        // ─── GLOBAL GUEST NAVIGATION GUARD ────────────────────────────────────────
        // Mencegah tamu mengakses halaman yang dilindungi SEBELUM request dikirim ke server.
        // Tidak ada page load, tidak ada network request — modal langsung muncul di halaman saat ini.
        //
        // Daftar path yang dilindungi (exact-match atau prefix-match):
        window._protectedPaths = [
            '/cart',
            '/wishlist',
            '/profile',
            '/checkout',
            '/orders',
            '/dashboard',
        ];

        // Periksa apakah sebuah URL path termasuk protected route
        window._isProtectedPath = function(pathname) {
            if (window.isUserLoggedIn) return false; // sudah login, lewatkan
            return window._protectedPaths.some(function(protected_) {
                // Exact match ATAU prefix match (mis. /orders/ABC-123 cocok dengan /orders)
                return pathname === protected_ || pathname.startsWith(protected_ + '/');
            });
        };

        // Intersep semua klik pada elemen <a> (termasuk klik pada child element)
        document.addEventListener('click', function(e) {
            // Temukan elemen <a> terdekat dari target klik
            const anchor = e.target.closest('a[href]');
            if (!anchor) return;

            const href = anchor.getAttribute('href');
            if (!href) return;

            // Abaikan anchor (#), link eksternal, mailto, tel, javascript:
            if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return;

            // Parse pathname dari href (handle relatif maupun absolut)
            let pathname;
            try {
                // Jika href relatif (/cart), URL constructor butuh base
                pathname = new URL(href, window.location.origin).pathname;
            } catch (_) {
                return; // URL tidak valid, biarkan browser tangani
            }

            // Cek apakah path ini dilindungi dan user adalah tamu
            if (window._isProtectedPath(pathname)) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.openLoginModal();
            }
        }, true); // capture phase = intersep sebelum Alpine/handler lain
        // ─────────────────────────────────────────────────────────────────────────

        window.storefrontLayout = function() {
            return {
                searchOpen: false,
                mobileMenuOpen: false,
                loginModalOpen: false,
                cartCount: 0,
                wishlistItems: [],
                wishlistCount: 0,
                authTab: 'login',
                authLoading: false,
                authErrors: {},

                switchAuthTab(tab) {
                    this.authTab = tab;
                    this.authErrors = {};
                },

                submitLogin(event) {
                    this.authLoading = true;
                    this.authErrors = {};
                    const form = event.target;
                    const formData = new FormData(form);

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: formData,
                    })
                    .then(async res => {
                        const data = await res.json().catch(() => ({}));
                        if (res.status === 422) {
                            this.authErrors = data.errors || {};
                            return;
                        }
                        if (!res.ok) {
                            this.authErrors = { email: [data.message || 'Terjadi kesalahan. Coba lagi.'] };
                            return;
                        }
                        if (data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(() => {
                        this.authErrors = { email: ['Terjadi kesalahan jaringan. Coba lagi.'] };
                    })
                    .finally(() => {
                        this.authLoading = false;
                    });
                },

                submitRegister(event) {
                    this.authLoading = true;
                    this.authErrors = {};
                    const form = event.target;
                    const formData = new FormData(form);

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: formData,
                    })
                    .then(async res => {
                        const data = await res.json().catch(() => ({}));
                        if (res.status === 422) {
                            this.authErrors = data.errors || {};
                            return;
                        }
                        if (!res.ok) {
                            this.authErrors = { email: [data.message || 'Terjadi kesalahan. Coba lagi.'] };
                            return;
                        }
                        // Setelah registrasi berhasil, arahkan ke halaman verifikasi email.
                        // Server menyertakan URL tujuan di data.redirect.
                        window.location.href = data.redirect || window.location.href;
                    })
                    .catch(() => {
                        this.authErrors = { email: ['Terjadi kesalahan jaringan. Coba lagi.'] };
                    })
                    .finally(() => {
                        this.authLoading = false;
                    });
                },

                init() {
                    this.wishlistItems = JSON.parse(localStorage.getItem('elcraft_wishlist_items') || '[]');
                    this.wishlistCount = this.wishlistItems.length;

                    window.addEventListener('storage', () => {
                        this.cartCount = parseInt(localStorage.getItem('elcraft_cart_count') || '0', 10);
                        this.wishlistItems = JSON.parse(localStorage.getItem('elcraft_wishlist_items') || '[]');
                        this.wishlistCount = this.wishlistItems.length;
                    });

                    window.addEventListener('pageshow', (event) => {
                        this.searchOpen = false;
                        this.mobileMenuOpen = false;
                        this.loginModalOpen = false;
                    });

                    if (window.isUserLoggedIn) {
                        fetch('/cart/count')
                            .then(res => res.json())
                            .then(data => {
                                if (data && typeof data.count !== 'undefined') {
                                    this.cartCount = data.count;
                                    localStorage.setItem('elcraft_cart_count', data.count);
                                }
                            })
                            .catch(err => console.error('Gagal menyinkronkan keranjang:', err));

                        fetch('/wishlist/count')
                            .then(res => res.json())
                            .then(data => {
                                if (data && typeof data.count !== 'undefined') {
                                    this.wishlistCount = data.count;
                                    this.wishlistItems = data.ids || [];
                                    localStorage.setItem('elcraft_wishlist_items', JSON.stringify(data.ids || []));
                                }
                            })
                            .catch(err => console.error('Gagal menyinkronkan wishlist:', err));
                    } else {
                        this.cartCount = 0;
                        this.wishlistCount = 0;
                        this.wishlistItems = [];
                        localStorage.setItem('elcraft_cart_count', '0');
                        localStorage.setItem('elcraft_wishlist_items', '[]');
                    }
                },

                toggleSearch() {
                    this.searchOpen = !this.searchOpen;
                    if (this.searchOpen) {
                        this.$nextTick(() => {
                            this.$refs.searchInput.focus();
                        });
                    }
                },

                toggleMobileMenu() {
                    this.mobileMenuOpen = !this.mobileMenuOpen;
                },

                toggleLoginModal() {
                    this.loginModalOpen = !this.loginModalOpen;
                },

                addToCart(productId, productName, productPrice) {
                    if (!window.isUserLoggedIn) {
                        this.toggleLoginModal();
                        return;
                    }

                    if (window.isAdminUser) {
                        window.showToast && window.showToast('Akun Administrator tidak dapat berbelanja. Mode Pratinjau Toko aktif.', 'info');
                        return;
                    }
                    
                    fetch('/cart/add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            variant_id: null,
                            quantity: 1
                        })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(err => { throw new Error(err.message || 'Gagal menambahkan produk.'); });
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (data && data.success) {
                            this.cartCount = data.cart_count;
                            localStorage.setItem('elcraft_cart_count', data.cart_count);
                            window.showToast && window.showToast(data.message, 'success');
                        }
                    })
                    .catch(err => {
                        window.showToast && window.showToast(err.message || 'Gagal menambahkan produk.', 'error');
                    });
                },

                toggleWishlistItem(productId, productName) {
                    if (!window.isUserLoggedIn) {
                        this.toggleLoginModal();
                        return;
                    }

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                    fetch('/wishlist/toggle', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ product_id: productId }),
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.wishlistCount = data.count;
                            if (data.in_wishlist) {
                                if (!this.wishlistItems.includes(productId)) {
                                    this.wishlistItems.push(productId);
                                }
                                window.showToast && window.showToast(`"${productName}" ditambahkan ke wishlist.`, 'success');
                            } else {
                                this.wishlistItems = this.wishlistItems.filter(id => id !== productId);
                                window.showToast && window.showToast(`"${productName}" dihapus dari wishlist.`, 'info');
                            }
                            localStorage.setItem('elcraft_wishlist_items', JSON.stringify(this.wishlistItems));
                        }
                    })
                    .catch(() => window.showToast && window.showToast('Gagal memperbarui wishlist. Coba lagi.', 'error'));
                },

                isInWishlist(productId) {
                    return this.wishlistItems.includes(productId);
                }
            };
        };

        window.addToCart = (productId, productName, productPrice) => {
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data && typeof data.addToCart === 'function') {
                    data.addToCart(productId, productName, productPrice);
                }
            }
        };

        window.updateCartBadge = (count) => {
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data) {
                    data.cartCount = count;
                    localStorage.setItem('elcraft_cart_count', count);
                }
            }
        };

        window.updateWishlistBadge = (count, ids = null) => {
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data) {
                    if (typeof data.wishlistCount !== 'undefined') {
                        data.wishlistCount = count;
                    }
                    if (ids !== null && typeof data.wishlistItems !== 'undefined') {
                        data.wishlistItems = ids;
                        localStorage.setItem('elcraft_wishlist_items', JSON.stringify(ids));
                    }
                }
            }
            localStorage.setItem('elcraft_wishlist_count', count);
        };

        window.toggleWishlistItem = (btnElement, productId, productName) => {
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data && typeof data.toggleWishlistItem === 'function') {
                    data.toggleWishlistItem(productId, productName);
                }
            }
        };
    </script>

    @stack('styles')
</head>
<body x-data="storefrontLayout()" class="bg-white text-warmBlack font-sans antialiased selection:bg-accent selection:text-brandDark">

    @if(auth()->check() && auth()->user()->role === 'admin')
        <!-- Admin Store Preview Mode Banner -->
        <div class="bg-gradient-to-r from-[#8E4034] via-[#A8584B] to-[#C17B6F] text-white px-4 py-2 text-xs font-medium sticky top-0 z-[60] shadow-sm border-b border-[#753229]/40">
            <div class="max-w-[1280px] mx-auto flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded-full bg-white/20 text-[10px] font-bold uppercase tracking-wider text-white border border-white/30">Mode Pratinjau Admin</span>
                    <span class="text-white/90">Anda sedang melihat toko sebagai <strong>{{ auth()->user()->name }}</strong>. Transaksi belanja dinonaktifkan untuk akun ini.</span>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1 bg-white text-[#8E4034] hover:bg-stone-100 rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-2xs">
                    <span>Kembali ke Dashboard Admin</span> &rarr;
                </a>
            </div>
        </div>
    @endif

    <!-- 1. STICKY NAVIGATION BAR (White Background) -->
    <header class="sticky {{ auth()->check() && auth()->user()->role === 'admin' ? 'top-[37px]' : 'top-0' }} w-full z-50 bg-white/95 backdrop-blur-md border-b border-warmLightGrey/60">
        <div class="max-w-[1280px] mx-auto px-6 md:px-12 lg:px-20 h-20 flex items-center justify-between">
            <!-- Left: Logo -->
            <a href="/" class="text-[22px] font-bold text-[#C17B6F] tracking-tight font-sans">el Craft</a>
            
            <!-- Center: Links (Desktop Only) -->
            <nav class="hidden md:flex space-x-9">
                <a href="/" class="{{ request()->is('/') ? 'text-[#C17B6F]' : 'text-[#78716C] hover:text-[#C17B6F]' }} font-semibold text-[12px] transition-colors duration-200 uppercase tracking-[0.15em]">Home</a>
                <a href="/products" class="{{ request()->is('products*') ? 'text-[#C17B6F]' : 'text-[#78716C] hover:text-[#C17B6F]' }} font-semibold text-[12px] transition-colors duration-200 uppercase tracking-[0.15em]">Produk</a>
                <a href="/#categories" class="text-[#78716C] hover:text-[#C17B6F] font-semibold text-[12px] transition-colors duration-200 uppercase tracking-[0.15em]">Kategori</a>
            </nav>
            
            <!-- Right: Icons -->
            <div class="flex items-center space-x-5">
                <!-- Search Trigger -->
                <button @click="toggleSearch()" class="text-warmBlack hover:text-brand transition-colors duration-200 relative p-1.5 flex items-center justify-center" aria-label="Search">
                    <span class="material-symbols-outlined !text-[24px]">search</span>
                </button>
                
                <!-- Wishlist Icon (Desktop Only) -->
                <a href="/wishlist" class="hidden md:flex text-warmBlack hover:text-brand transition-colors duration-200 relative p-1.5 items-center justify-center" aria-label="Wishlist">
                    <span class="material-symbols-outlined !text-[24px]">favorite</span>
                    <span class="absolute -top-0.5 -right-0.5 bg-brand text-white text-[9px] font-semibold w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300" x-show="wishlistCount > 0" x-text="wishlistCount" x-cloak>0</span>
                </a>
                
                <!-- Cart Icon with Badge Count -->
                <a href="/cart" class="text-warmBlack hover:text-brand transition-colors duration-200 relative p-1.5 flex items-center justify-center" aria-label="Cart">
                    <span class="material-symbols-outlined !text-[24px]">shopping_bag</span>
                    <span class="absolute -top-0.5 -right-0.5 bg-brand text-white text-[9px] font-semibold w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300" x-show="cartCount > 0" x-text="cartCount" x-cloak>0</span>
                </a>
                
                {{-- User Profile Icon (Desktop Only) — links to Order History --}}
                <div class="hidden md:flex items-center relative" x-data="{ profileOpen: false }">
                    <button @click="profileOpen = !profileOpen" class="text-warmBlack hover:text-brand transition-colors duration-200 p-1.5 flex items-center justify-center" aria-label="User menu" aria-haspopup="true">
                        <span class="material-symbols-outlined !text-[24px]">person</span>
                    </button>
                    @auth
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                        class="absolute right-0 top-full mt-2 w-48 bg-white border border-warmLightGrey rounded-card shadow-md py-1 z-50">
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs text-brand font-bold bg-brand-50/60 hover:bg-brand-50 transition-colors">
                                <span class="material-symbols-outlined !text-[16px]">admin_panel_settings</span> Panel Admin
                            </a>
                            <div class="border-t border-warmLightGrey/60 my-1"></div>
                        @endif
                        <a href="{{ route('orders.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs text-warmBlack hover:bg-warmCream hover:text-brand transition-colors">
                            <span class="material-symbols-outlined !text-[16px]">receipt_long</span> Pesanan Saya
                        </a>
                        <a href="{{ route('wishlist.index') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs text-warmBlack hover:bg-warmCream hover:text-brand transition-colors">
                            <span class="material-symbols-outlined !text-[16px]">favorite</span> Wishlist Saya
                        </a>
                        <a href="{{ route('profile.addresses') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs text-warmBlack hover:bg-warmCream hover:text-brand transition-colors">
                            <span class="material-symbols-outlined !text-[16px]">location_on</span> Alamat Saya
                        </a>
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-4 py-2.5 text-xs text-warmBlack hover:bg-warmCream hover:text-brand transition-colors">
                            <span class="material-symbols-outlined !text-[16px]">manage_accounts</span> Profil
                        </a>
                        <div class="border-t border-warmLightGrey/60 my-1"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2.5 text-xs text-red-500 hover:bg-red-50 transition-colors">
                                <span class="material-symbols-outlined !text-[16px]">logout</span> Keluar
                            </button>
                        </form>
                    </div>
                    @else
                    <div x-show="profileOpen" @click.outside="profileOpen = false" x-cloak
                        class="absolute right-0 top-full mt-2 w-36 bg-white border border-warmLightGrey rounded-card shadow-md py-1 z-50">
                        <button type="button" @click="profileOpen = false; switchAuthTab('login'); toggleLoginModal()" class="w-full text-left block px-4 py-2.5 text-xs text-warmBlack hover:bg-warmCream hover:text-brand transition-colors">Masuk</button>
                        <button type="button" @click="profileOpen = false; switchAuthTab('register'); toggleLoginModal()" class="w-full text-left block px-4 py-2.5 text-xs text-warmBlack hover:bg-warmCream hover:text-brand transition-colors">Daftar</button>
                    </div>
                    @endauth
                </div>
                
                <!-- Hamburger Menu Trigger (Mobile Only) -->
                <button @click="toggleMobileMenu()" class="md:hidden text-warmBlack hover:text-brand transition-colors duration-200 p-1.5 flex items-center justify-center" aria-label="Menu">
                    <span class="material-symbols-outlined !text-[26px]">menu</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Slide-Down Search Overlay -->
    <div id="search-overlay" x-cloak class="fixed inset-0 z-50 bg-warmBlack/30 backdrop-blur-xs transition-all duration-300 opacity-0 pointer-events-none" :class="searchOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'">
        <div class="bg-white w-full py-6 border-b border-warmLightGrey transition-transform duration-300" :class="searchOpen ? 'translate-y-0' : '-translate-y-full'">
            <form action="/products" method="GET" class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 flex items-center justify-between">
                <div class="flex-1 max-w-2xl flex items-center border-b border-brand py-2">
                    <span class="material-symbols-outlined text-warmGrey mr-3">search</span>
                    <input type="text" x-ref="searchInput" name="search" value="{{ request('search') }}" placeholder="Cari aksesoris impianmu..." aria-label="Cari aksesoris impianmu" class="w-full bg-transparent border-none outline-none text-warmBlack placeholder-warmGrey/50 font-sans text-base focus:ring-0">
                </div>
                <div class="flex items-center ml-6 space-x-4">
                    <button type="submit" class="px-4 py-2 bg-brand hover:bg-brandDark text-white text-xs font-semibold uppercase tracking-wider rounded-btn transition-colors duration-200">Cari</button>
                    <button type="button" @click="toggleSearch()" class="text-warmBlack hover:text-brand flex items-center justify-center" aria-label="Close search">
                        <span class="material-symbols-outlined !text-[26px]">close</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mobile Slide-out Menu Drawer -->
    <div id="mobile-drawer" x-cloak class="fixed inset-0 z-50 bg-warmBlack/30 backdrop-blur-xs transition-all duration-300 opacity-0 pointer-events-none" :class="mobileMenuOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'">
        <div class="bg-white w-72 h-full absolute right-0 top-0 shadow-lg p-6 flex flex-col justify-between transition-transform duration-300" :class="mobileMenuOpen ? 'translate-x-0' : 'translate-x-full'">
            <div>
                <div class="flex justify-between items-center mb-8">
                    <span class="text-xl font-semibold text-brand tracking-wide">el Craft</span>
                    <button @click="toggleMobileMenu()" class="text-warmBlack hover:text-brand flex items-center justify-center" aria-label="Close menu">
                        <span class="material-symbols-outlined !text-[24px]">close</span>
                    </button>
                </div>
                <nav class="flex flex-col space-y-5">
                    <a href="/" class="text-warmBlack hover:text-brand font-medium text-base border-b border-warmLightGrey/50 pb-2 transition-colors duration-200">Home</a>
                    <a href="/products" class="text-warmBlack hover:text-brand font-medium text-base border-b border-warmLightGrey/50 pb-2 transition-colors duration-200">Produk</a>
                    <a href="/#categories" @click="toggleMobileMenu()" class="text-warmBlack hover:text-brand font-medium text-base border-b border-warmLightGrey/50 pb-2 transition-colors duration-200">Kategori</a>
                </nav>
            </div>
            
            @auth
                <div class="border-t border-warmLightGrey pt-6 flex flex-col space-y-3">
                    @if(auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="w-full flex items-center gap-2 py-2.5 text-sm text-brand font-bold bg-brand-50/60 px-3 rounded-lg hover:bg-brand-50 transition-colors">
                            <span class="material-symbols-outlined !text-[18px]">admin_panel_settings</span> Panel Admin
                        </a>
                    @endif
                    <a href="{{ route('orders.index') }}" class="w-full flex items-center gap-2 py-2.5 text-sm text-warmBlack hover:text-brand font-medium transition-colors">
                        <span class="material-symbols-outlined !text-[18px]">receipt_long</span> Pesanan Saya
                    </a>
                    <a href="{{ route('wishlist.index') }}" class="w-full flex items-center gap-2 py-2.5 text-sm text-warmBlack hover:text-brand font-medium transition-colors">
                        <span class="material-symbols-outlined !text-[18px]">favorite</span> Wishlist Saya
                    </a>
                    <a href="{{ route('profile.addresses') }}" class="w-full flex items-center gap-2 py-2.5 text-sm text-warmBlack hover:text-brand font-medium transition-colors">
                        <span class="material-symbols-outlined !text-[18px]">location_on</span> Alamat Saya
                    </a>
                    <a href="{{ route('profile.edit') }}" class="w-full text-center py-2.5 bg-brand text-white font-semibold text-sm rounded-btn hover:bg-brandDark transition-colors duration-200">Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-center py-2.5 border border-brand text-brand font-semibold text-sm rounded-btn hover:bg-warmCream transition-colors duration-200">Keluar</button>
                    </form>
                </div>
            @else
                <div class="border-t border-warmLightGrey pt-6 flex flex-col space-y-3">
                    <button type="button" @click="toggleMobileMenu(); switchAuthTab('login'); toggleLoginModal()" class="w-full text-center py-2.5 border border-brand text-brand font-semibold text-sm rounded-btn hover:bg-warmCream transition-colors duration-200">Masuk</button>
                    <button type="button" @click="toggleMobileMenu(); switchAuthTab('register'); toggleLoginModal()" class="w-full text-center py-2.5 bg-brand text-white font-semibold text-sm rounded-btn hover:bg-brandDark transition-colors duration-200">Daftar</button>
                </div>
            @endauth
        </div>
    </div>

    <!-- Main Content Slot -->
    <main class="w-full">
        @yield('content')
    </main>

    <!-- 7. FOOTER SECTION (Warm Black Background) -->
    <footer class="bg-warmBlack text-white pt-16 pb-20 border-t border-warmGrey/20">
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 grid grid-cols-1 md:grid-cols-4 gap-10">
            <!-- Brand Column -->
            <div class="flex flex-col space-y-4">
                <span class="text-2xl font-semibold text-brand tracking-wide font-sans">el Craft</span>
                <p class="text-xs text-warmGrey leading-relaxed">Menyediakan berbagai aksesoris wanita berkualitas premium, dirancang dengan penuh ketelitian untuk memancarkan keanggunan abadi di setiap momen penting Anda.</p>
                <div class="flex space-x-3 pt-2">
                    <a href="#" class="w-8 h-8 rounded-full border border-warmGrey/30 flex items-center justify-center text-warmGrey hover:text-brand hover:border-brand transition-colors duration-200">
                        <span class="material-symbols-outlined !text-[18px]">share</span>
                    </a>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div>
                <h4 class="text-sm font-semibold tracking-wider uppercase text-brand mb-5">Belanja</h4>
                <ul class="space-y-3 text-xs text-warmGrey">
                    <li><a href="/products?category=gelang" class="hover:text-white transition-colors duration-200">Gelang</a></li>
                    <li><a href="/products?category=kalung" class="hover:text-white transition-colors duration-200">Kalung</a></li>
                    <li><a href="/products?category=cincin" class="hover:text-white transition-colors duration-200">Cincin</a></li>
                    <li><a href="/products?category=bros" class="hover:text-white transition-colors duration-200">Bros</a></li>
                    <li><a href="/products?category=anting" class="hover:text-white transition-colors duration-200">Anting</a></li>
                </ul>
            </div>
            
            <!-- Information -->
            <div>
                <h4 class="text-sm font-semibold tracking-wider uppercase text-brand mb-5">Informasi</h4>
                <ul class="space-y-3 text-xs text-warmGrey">
                    <li><a href="#" class="hover:text-white transition-colors duration-200">Syarat & Ketentuan</a></li>
                    <li><a href="#" class="hover:text-white transition-colors duration-200">Kebijakan Privasi</a></li>
                    <li><a href="#" class="hover:text-white transition-colors duration-200">Cara Pembayaran</a></li>
                    <li><a href="#" class="hover:text-white transition-colors duration-200">Info Pengiriman</a></li>
                </ul>
            </div>
            
            <!-- Contact -->
            <div class="flex flex-col space-y-4">
                <h4 class="text-sm font-semibold tracking-wider uppercase text-brand mb-1">Hubungi Kami</h4>
                <div class="flex items-start space-x-3 text-xs text-warmGrey">
                    <span class="material-symbols-outlined text-brand !text-[18px] mt-0.5">location_on</span>
                    <span>Jl. Craft No. 123, Jakarta Selatan, Indonesia</span>
                </div>
                <div class="flex items-center space-x-3 text-xs text-warmGrey">
                    <span class="material-symbols-outlined text-brand !text-[18px]">phone</span>
                    <span>+62 812-3456-7890</span>
                </div>
                <div class="flex items-center space-x-3 text-xs text-warmGrey">
                    <span class="material-symbols-outlined text-brand !text-[18px]">mail</span>
                    <span>support@elcraft.id</span>
                </div>
            </div>
        </div>
        
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 mt-12 pt-8 border-t border-warmGrey/10 flex flex-col md:flex-row justify-between items-center text-xs text-warmGrey space-y-4 md:space-y-0">
            <span>&copy; {{ date('Y') }} el Craft. All rights reserved.</span>
            <div class="flex space-x-6">
                <span>Made with ♥ for timeless style.</span>
            </div>
        </div>
    </footer>

    <!-- 8. BOTTOM MOBILE DOCK NAVIGATION -->
    @unless(request()->routeIs('products.show'))
    <div class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-warmLightGrey z-40 grid grid-cols-5 h-16 pb-safe">
        <a href="/" class="flex flex-col items-center justify-center {{ request()->is('/') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} w-full h-full">
            <span class="material-symbols-outlined !text-[22px]" style="{{ request()->is('/') ? 'font-variation-settings:\'FILL\' 1' : '' }}">home</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Home</span>
        </a>
        <button @click="toggleSearch()" class="flex flex-col items-center justify-center {{ request()->is('products*') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} w-full h-full">
            <span class="material-symbols-outlined !text-[22px]" style="{{ request()->is('products*') ? 'font-variation-settings:\'FILL\' 1' : '' }}">search</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Search</span>
        </button>
        <a href="/cart" class="flex flex-col items-center justify-center {{ request()->is('cart*') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} relative w-full h-full">
            <span class="material-symbols-outlined !text-[22px]" style="{{ request()->is('cart*') ? 'font-variation-settings:\'FILL\' 1' : '' }}">shopping_bag</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Cart</span>
            <span class="absolute top-0.5 right-2 bg-brand text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300" x-show="cartCount > 0" x-text="cartCount" x-cloak>0</span>
        </a>
        <a href="/wishlist" class="flex flex-col items-center justify-center {{ request()->is('wishlist*') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} relative w-full h-full">
            <span class="material-symbols-outlined !text-[22px]" style="{{ request()->is('wishlist*') ? 'font-variation-settings:\'FILL\' 1' : '' }}">favorite</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Wishlist</span>
            <span class="absolute top-0.5 right-2 bg-brand text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300" x-show="wishlistCount > 0" x-text="wishlistCount" x-cloak>0</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="flex flex-col items-center justify-center {{ (request()->is('profile*') || request()->is('orders*') || request()->is('dashboard')) ? 'text-brand' : 'text-warmGrey hover:text-brand' }} w-full h-full">
            <span class="material-symbols-outlined !text-[22px]" style="{{ (request()->is('profile*') || request()->is('orders*') || request()->is('dashboard')) ? 'font-variation-settings:\'FILL\' 1' : '' }}">person</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Profile</span>
        </a>
    </div>
    @endunless

    <!-- Tabbed Login & Register Modal -->
    <div id="login-modal" x-cloak class="fixed inset-0 z-[60] bg-warmBlack/40 backdrop-blur-xs flex items-center justify-center transition-all duration-300 opacity-0 pointer-events-none p-4 md:p-6" :class="loginModalOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'">
        <div class="bg-white border border-[#E8B4AC]/40 rounded-2xl shadow-lg transition-all duration-300 overflow-hidden flex flex-col max-h-[90vh]" style="width: 100%; max-width: 400px; max-height: 90vh; display: flex; flex-direction: column; box-sizing: border-box; overflow: hidden;" :class="loginModalOpen ? 'scale-100' : 'scale-95'">
            
            <!-- Tab Navigation Header (Sticky) -->
            <div class="flex border-b border-[#E8B4AC]/40 relative bg-[#FDFBFB] shrink-0 sticky top-0 z-10" style="position: sticky; top: 0; z-index: 10; flex-shrink: 0;">
                <button
                    type="button"
                    @click="switchAuthTab('login')"
                    class="flex-1 py-3 text-sm font-semibold transition-colors duration-200 border-b-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C17B6F]/40 select-none"
                    :class="authTab === 'login' ? 'text-[#C17B6F] border-[#C17B6F]' : 'text-[#A98077] border-transparent hover:text-[#9E5A4E]'">
                    Masuk
                </button>
                <button
                    type="button"
                    @click="switchAuthTab('register')"
                    class="flex-1 py-3 text-sm font-semibold transition-colors duration-200 border-b-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#C17B6F]/40 select-none"
                    :class="authTab === 'register' ? 'text-[#C17B6F] border-[#C17B6F]' : 'text-[#A98077] border-transparent hover:text-[#9E5A4E]'">
                    Daftar
                </button>
                <button
                    type="button"
                    @click="toggleLoginModal()"
                    class="absolute right-2 top-2 p-1.5 text-[#A98077] hover:text-warmBlack rounded-full hover:bg-warmCream transition-colors focus:outline-none"
                    aria-label="Tutup modal">
                    <span class="material-symbols-outlined !text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="p-6 overflow-y-auto" style="overflow-y: auto; -webkit-overflow-scrolling: touch; flex: 1 1 auto; max-height: calc(90vh - 49px); box-sizing: border-box;">
                
                <!-- ─── LOGIN PANEL ─── -->
                <div x-show="authTab === 'login'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="text-center mb-5">
                        <span class="text-xl font-bold text-[#C17B6F] tracking-tight font-sans inline-block">el Craft</span>
                        <p class="text-xs font-medium text-[#A98077] mt-0.5">Masuk ke akun Anda</p>
                    </div>

                    {{-- ─── Post-reset (or other) status message ─────────────────────── --}}
                    @if (session('status'))
                        <div class="mb-4 px-4 py-3 rounded-xl border text-xs font-medium text-center"
                             style="background:#F0FDF4;border-color:#BBF7D0;color:#15803D;">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form action="{{ route('login') }}" method="POST" @submit.prevent="submitLogin($event)" id="modal-login-form">
                        @csrf

                        <!-- Email -->
                        <div class="mb-3.5" style="margin-bottom: 16px;">
                            <label for="modal-login-email" class="block text-xs font-medium text-[#5F5E5A] mb-1.5" style="margin-bottom: 6px;">Email</label>
                            <div class="relative" style="position: relative;">
                                <span style="position: absolute; top: 50%; left: 12px; transform: translateY(-50%); display: flex; align-items: center; pointer-events: none;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C8A09A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                                        <polyline points="2,4 12,13 22,4"/>
                                    </svg>
                                </span>
                                <input
                                    id="modal-login-email"
                                    type="email"
                                    name="email"
                                    required
                                    autocomplete="username"
                                    placeholder="nama@email.com"
                                    style="
                                        width: 100%;
                                        padding: 9px 12px 9px 34px;
                                        font-size: 13px;
                                        color: #9E5A4E;
                                        border: 1px solid #E8B4AC;
                                        border-radius: 10px;
                                        background: #fff;
                                        outline: none;
                                        transition: border-color .2s, box-shadow .2s;
                                        box-sizing: border-box;
                                    "
                                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                                >
                            </div>
                            <template x-if="authErrors.email">
                                <p class="mt-1.5 text-xs text-red-500" style="margin-top: 6px;" x-text="authErrors.email[0]"></p>
                            </template>
                        </div>

                        <!-- Password -->
                        <div class="mb-4" style="margin-bottom: 18px;">
                            <label for="modal-login-password" class="block text-xs font-medium text-[#5F5E5A] mb-1.5" style="margin-bottom: 6px;">Kata Sandi</label>
                            <div class="relative" style="position: relative;" x-data="{ showPassword: false }">
                                <input
                                    id="modal-login-password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    style="
                                        width: 100%;
                                        padding: 9px 38px 9px 12px;
                                        font-size: 13px;
                                        color: #9E5A4E;
                                        border: 1px solid #E8B4AC;
                                        border-radius: 10px;
                                        background: #fff;
                                        outline: none;
                                        transition: border-color .2s, box-shadow .2s;
                                        box-sizing: border-box;
                                    "
                                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                                >
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#A98077] hover:text-[#C17B6F] transition-colors focus:outline-none"
                                    style="position: absolute; top: 0; right: 0; bottom: 0; width: 44px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; cursor: pointer; padding: 0;"
                                    tabindex="-1"
                                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                    <svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                            <template x-if="authErrors.password">
                                <p class="mt-1.5 text-xs text-red-500" style="margin-top: 6px;" x-text="authErrors.password[0]"></p>
                            </template>
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between mb-5" style="margin-bottom: 20px;">
                            <label for="modal-remember-me" class="flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    id="modal-remember-me"
                                    type="checkbox"
                                    name="remember"
                                    class="w-4 h-4 rounded border-[#E8B4AC] text-[#C17B6F] focus:ring-[#C17B6F]/30 cursor-pointer"
                                    style="accent-color: #C17B6F;"
                                >
                                <span class="text-xs text-[#A98077] font-medium">Ingat saya</span>
                            </label>
                            <a href="{{ route('password.request') }}"
                               class="text-xs text-[#C17B6F] hover:text-[#9E5A4E] font-semibold transition-colors duration-200">
                                Lupa kata sandi?
                            </a>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="authLoading"
                            class="w-full py-2.5 bg-[#C17B6F] hover:bg-[#9E5A4E] disabled:opacity-60 text-white font-semibold text-sm rounded-xl transition-colors duration-200 shadow-xs flex items-center justify-center gap-2">
                            <span x-text="authLoading ? 'Memproses...' : 'Masuk'"></span>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="flex items-center gap-3 my-4">
                        <div class="flex-1 h-px bg-[#E8B4AC]"></div>
                        <span class="text-xs font-medium text-[#A98077]">atau</span>
                        <div class="flex-1 h-px bg-[#E8B4AC]"></div>
                    </div>

                    <!-- Google Button -->
                    <a href="{{ route('auth.google.redirect') }}"
                       class="w-full flex items-center justify-center gap-3 py-2.5 px-4 rounded-xl border border-[#E8B4AC] bg-white hover:bg-[#F5F0EE] text-[#9E5A4E] font-semibold text-sm transition-colors duration-200 shadow-xs">
                        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                            <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
                            <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                            <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                        </svg>
                        Masuk dengan Google
                    </a>

                    <!-- Switch to Register -->
                    <p class="mt-4 text-center text-xs text-[#A98077]">
                        Belum punya akun?
                        <button type="button" @click="switchAuthTab('register')" class="text-[#C17B6F] hover:text-[#9E5A4E] font-semibold transition-colors">
                            Daftar
                        </button>
                    </p>
                </div>

                <!-- ─── REGISTER PANEL ─── -->
                <div x-show="authTab === 'register'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="text-center mb-4" style="margin-bottom: 14px;">
                        <span class="text-xl font-bold text-[#C17B6F] tracking-tight font-sans inline-block">el Craft</span>
                        <p class="text-xs font-medium text-[#A98077] mt-0.5">Daftarkan akun baru Anda</p>
                    </div>

                    <form action="{{ route('register') }}" method="POST" @submit.prevent="submitRegister($event)" id="modal-register-form">
                        @csrf

                        <!-- Name -->
                        <div class="mb-3" style="margin-bottom: 12px;">
                            <label for="modal-register-name" class="block text-xs font-medium text-[#5F5E5A] mb-1" style="margin-bottom: 4px;">Nama Lengkap</label>
                            <div class="relative" style="position: relative;">
                                <span style="position: absolute; top: 50%; left: 12px; transform: translateY(-50%); display: flex; align-items: center; pointer-events: none;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C8A09A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </span>
                                <input
                                    id="modal-register-name"
                                    type="text"
                                    name="name"
                                    required
                                    autocomplete="name"
                                    placeholder="Nama Anda"
                                    style="
                                        width: 100%;
                                        padding: 9px 12px 9px 34px;
                                        font-size: 13px;
                                        color: #9E5A4E;
                                        border: 1px solid #E8B4AC;
                                        border-radius: 10px;
                                        background: #fff;
                                        outline: none;
                                        transition: border-color .2s, box-shadow .2s;
                                        box-sizing: border-box;
                                    "
                                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                                >
                            </div>
                            <template x-if="authErrors.name">
                                <p class="mt-1 text-xs text-red-500" style="margin-top: 4px;" x-text="authErrors.name[0]"></p>
                            </template>
                        </div>

                        <!-- Email -->
                        <div class="mb-3" style="margin-bottom: 12px;">
                            <label for="modal-register-email" class="block text-xs font-medium text-[#5F5E5A] mb-1" style="margin-bottom: 4px;">Email</label>
                            <div class="relative" style="position: relative;">
                                <span style="position: absolute; top: 50%; left: 12px; transform: translateY(-50%); display: flex; align-items: center; pointer-events: none;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C8A09A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                                        <polyline points="2,4 12,13 22,4"/>
                                    </svg>
                                </span>
                                <input
                                    id="modal-register-email"
                                    type="email"
                                    name="email"
                                    required
                                    autocomplete="username"
                                    placeholder="nama@email.com"
                                    style="
                                        width: 100%;
                                        padding: 9px 12px 9px 34px;
                                        font-size: 13px;
                                        color: #9E5A4E;
                                        border: 1px solid #E8B4AC;
                                        border-radius: 10px;
                                        background: #fff;
                                        outline: none;
                                        transition: border-color .2s, box-shadow .2s;
                                        box-sizing: border-box;
                                    "
                                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                                >
                            </div>
                            <template x-if="authErrors.email">
                                <p class="mt-1 text-xs text-red-500" style="margin-top: 4px;" x-text="authErrors.email[0]"></p>
                            </template>
                        </div>

                        <!-- Password -->
                        <div class="mb-3" style="margin-bottom: 12px;">
                            <label for="modal-register-password" class="block text-xs font-medium text-[#5F5E5A] mb-1" style="margin-bottom: 4px;">Kata Sandi</label>
                            <div class="relative" style="position: relative;" x-data="{ showPassword: false }">
                                <input
                                    id="modal-register-password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                    style="
                                        width: 100%;
                                        padding: 9px 38px 9px 12px;
                                        font-size: 13px;
                                        color: #9E5A4E;
                                        border: 1px solid #E8B4AC;
                                        border-radius: 10px;
                                        background: #fff;
                                        outline: none;
                                        transition: border-color .2s, box-shadow .2s;
                                        box-sizing: border-box;
                                    "
                                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                                >
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#A98077] hover:text-[#C17B6F] transition-colors focus:outline-none"
                                    style="position: absolute; top: 0; right: 0; bottom: 0; width: 44px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; cursor: pointer; padding: 0;"
                                    tabindex="-1"
                                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                    <svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                            <template x-if="authErrors.password">
                                <p class="mt-1 text-xs text-red-500" style="margin-top: 4px;" x-text="authErrors.password[0]"></p>
                            </template>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4" style="margin-bottom: 16px;">
                            <label for="modal-register-password-confirmation" class="block text-xs font-medium text-[#5F5E5A] mb-1" style="margin-bottom: 4px;">Konfirmasi Kata Sandi</label>
                            <div class="relative" style="position: relative;" x-data="{ showPasswordConfirm: false }">
                                <input
                                    id="modal-register-password-confirmation"
                                    :type="showPasswordConfirm ? 'text' : 'password'"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                    style="
                                        width: 100%;
                                        padding: 9px 38px 9px 12px;
                                        font-size: 13px;
                                        color: #9E5A4E;
                                        border: 1px solid #E8B4AC;
                                        border-radius: 10px;
                                        background: #fff;
                                        outline: none;
                                        transition: border-color .2s, box-shadow .2s;
                                        box-sizing: border-box;
                                    "
                                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                                >
                                <button
                                    type="button"
                                    @click="showPasswordConfirm = !showPasswordConfirm"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#A98077] hover:text-[#C17B6F] transition-colors focus:outline-none"
                                    style="position: absolute; top: 0; right: 0; bottom: 0; width: 44px; display: flex; align-items: center; justify-content: center; background: transparent; border: none; cursor: pointer; padding: 0;"
                                    tabindex="-1"
                                    :aria-label="showPasswordConfirm ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                    <svg x-show="!showPasswordConfirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg x-show="showPasswordConfirm" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                        <line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="authLoading"
                            class="w-full py-2.5 bg-[#C17B6F] hover:bg-[#9E5A4E] disabled:opacity-60 text-white font-semibold text-sm rounded-xl transition-colors duration-200 shadow-xs flex items-center justify-center gap-2">
                            <span x-text="authLoading ? 'Memproses...' : 'Daftar'"></span>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="flex items-center gap-3 my-3" style="margin-top: 12px; margin-bottom: 12px;">
                        <div class="flex-1 h-px bg-[#E8B4AC]"></div>
                        <span class="text-xs font-medium text-[#A98077]">atau</span>
                        <div class="flex-1 h-px bg-[#E8B4AC]"></div>
                    </div>

                    <!-- Google Button -->
                    <a href="{{ route('auth.google.redirect') }}"
                       class="w-full flex items-center justify-center gap-3 py-2.5 px-4 rounded-xl border border-[#E8B4AC] bg-white hover:bg-[#F5F0EE] text-[#9E5A4E] font-semibold text-sm transition-colors duration-200 shadow-xs">
                        <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                            <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
                            <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                            <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                        </svg>
                        Daftar dengan Google
                    </a>

                    <!-- Switch to Login -->
                    <p class="mt-3 text-center text-xs text-[#A98077]" style="margin-top: 12px;">
                        Sudah punya akun?
                        <button type="button" @click="switchAuthTab('login')" class="text-[#C17B6F] hover:text-[#9E5A4E] font-semibold transition-colors">
                            Masuk
                        </button>
                    </p>
                </div>

            </div>
        </div>
    </div>

    <!-- Toast Notification Overlay Container -->
    <div id="toast-container" class="fixed top-24 right-5 z-[100] flex flex-col space-y-3 pointer-events-none"></div>

    <!-- Client-side Interactive Functionality Script -->
    <script>
        // Subscriptions Form Handler (non-ajax welcome form)
        function handleSubscribe(e) {
            e.preventDefault();
            const input = e.target.querySelector('input');
            const email = input.value;
            showToast(`Terima kasih! ${email} telah bergabung dengan Inner Circle kami.`);
            input.value = '';
        }
    </script>
    @stack('scripts')
</body>
</html>
