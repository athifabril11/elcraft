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
        {{-- Floating Admin Preview Chip — overlays content, tidak mendorong halaman ke bawah --}}
        <div id="admin-preview-chip"
             x-data="{ visible: true }"
             x-show="visible"
             x-cloak
             style="position:fixed;top:88px;right:16px;z-index:58;"
             class="flex items-start gap-2 bg-white border border-[#E8B4AC]/70 rounded-xl shadow-lg px-3 py-2.5 max-w-[260px]"
             role="status"
             aria-label="Mode Pratinjau Admin — Transaksi belanja dinonaktifkan untuk akun ini.">
            {{-- Colored dot --}}
            <span class="mt-[3px] flex-shrink-0 w-2 h-2 rounded-full bg-brand"></span>
            {{-- Text block — all assertSee() strings present in raw HTML --}}
            <div class="flex-1 min-w-0">
                <p class="text-[11px] font-bold text-brandDark leading-tight">Mode Pratinjau Admin</p>
                <p class="text-[10px] text-warmGrey leading-snug mt-0.5">Transaksi belanja dinonaktifkan untuk akun ini.</p>
                <a href="{{ route('admin.dashboard') }}"
                   class="inline-flex items-center gap-1 mt-1.5 text-[10px] font-semibold text-brand hover:text-brandDark transition-colors">
                    <span>Kembali ke Dashboard Admin</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                </a>
            </div>
            {{-- Close (✕) button --}}
            <button @click="visible = false"
                    class="flex-shrink-0 text-warmGrey hover:text-warmBlack transition-colors p-0.5 -mt-0.5 -mr-0.5"
                    aria-label="Tutup notifikasi pratinjau">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    @endif

    <!-- 1. STICKY NAVIGATION BAR (White Background) -->
    <header class="sticky top-0 w-full z-50 bg-white/95 backdrop-blur-md border-b border-warmLightGrey/60">
        <div class="max-w-[1280px] mx-auto px-6 md:px-12 lg:px-20 h-20 flex items-center justify-between">
            <!-- Left: Logo -->
            <a href="/" class="text-[22px] font-bold text-brand tracking-tight font-sans">el Craft</a>
            
            <!-- Center: Links (Desktop Only) -->
            <nav class="hidden md:flex space-x-9">
                <a href="/" class="{{ request()->is('/') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} font-semibold text-[12px] transition-colors duration-200 uppercase tracking-[0.15em]">Home</a>
                <a href="/products" class="{{ request()->is('products*') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} font-semibold text-[12px] transition-colors duration-200 uppercase tracking-[0.15em]">Produk</a>
                <a href="/#categories" class="text-warmGrey hover:text-brand font-semibold text-[12px] transition-colors duration-200 uppercase tracking-[0.15em]">Kategori</a>
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
                    <a href="https://www.instagram.com/elcraft.id_" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="w-8 h-8 rounded-full border border-warmGrey/30 flex items-center justify-center text-warmGrey hover:text-brand hover:border-brand transition-colors duration-200">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    </a>
                    <a href="https://www.tiktok.com/@elcraft.id0" target="_blank" rel="noopener noreferrer" aria-label="TikTok" class="w-8 h-8 rounded-full border border-warmGrey/30 flex items-center justify-center text-warmGrey hover:text-brand hover:border-brand transition-colors duration-200">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
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
                <h4 class="text-sm font-semibold tracking-wider uppercase text-brand mb-5">Hubungi Kami</h4>
                <div class="flex items-center space-x-3 text-xs text-warmGrey">
                    <span class="material-symbols-outlined text-brand !text-[18px]">call</span>
                    <a href="tel:+6283854410513" class="hover:text-white transition-colors duration-200">+62 838-5441-0513</a>
                </div>
                <div class="flex items-center space-x-3 text-xs text-warmGrey">
                    <span class="material-symbols-outlined text-brand !text-[18px]">mail</span>
                    <a href="mailto:elcraftid1402@gmail.com" class="hover:text-white transition-colors duration-200">elcraftid1402@gmail.com</a>
                </div>
                <div class="flex items-start space-x-3 text-xs text-warmGrey">
                    <span class="material-symbols-outlined text-brand !text-[18px]">location_on</span>
                    <span class="leading-relaxed">Dusun Krajan Barat, Desa Sukojember, Kecamatan Jelbuk, Kabupaten Jember, Jawa Timur 68192</span>
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
                    class="flex-1 py-3 text-sm font-semibold transition-colors duration-200 border-b-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 select-none"
                    :class="authTab === 'login' ? 'text-brand border-brand' : 'text-[#A98077] border-transparent hover:text-brandDark'">
                    Masuk
                </button>
                <button
                    type="button"
                    @click="switchAuthTab('register')"
                    class="flex-1 py-3 text-sm font-semibold transition-colors duration-200 border-b-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 select-none"
                    :class="authTab === 'register' ? 'text-brand border-brand' : 'text-[#A98077] border-transparent hover:text-brandDark'">
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
                        <span class="text-xl font-bold text-brand tracking-tight font-sans inline-block">el Craft</span>
                        <p class="text-xs font-medium text-[#A98077] mt-0.5">Masuk ke akun Anda</p>
                    </div>

                    {{-- ─── Post-reset (or other) status message ─────────────────────── --}}
                    @if (session('status'))
                        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.duration.300ms
                             class="mb-4 px-4 py-3 rounded-xl border text-xs font-medium text-center shadow-2xs"
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
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#A98077] hover:text-brand transition-colors focus:outline-none"
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
                                    class="w-4 h-4 rounded border-[#E8B4AC] text-brand focus:ring-brand/30 cursor-pointer"
                                    style="accent-color: #C17B6F;"
                                >
                                <span class="text-xs text-[#A98077] font-medium">Ingat saya</span>
                            </label>
                            <a href="{{ route('password.request') }}"
                               class="text-xs text-brand hover:text-brandDark font-semibold transition-colors duration-200">
                                Lupa kata sandi?
                            </a>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="authLoading"
                            class="w-full py-2.5 bg-brand hover:bg-brandDark disabled:opacity-60 text-white font-semibold text-sm rounded-xl transition-colors duration-200 shadow-xs flex items-center justify-center gap-2">
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
                       class="w-full flex items-center justify-center gap-3 py-2.5 px-4 rounded-xl border border-[#E8B4AC] bg-white hover:bg-[#F5F0EE] text-brandDark font-semibold text-sm transition-colors duration-200 shadow-xs">
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
                        <button type="button" @click="switchAuthTab('register')" class="text-brand hover:text-brandDark font-semibold transition-colors">
                            Daftar
                        </button>
                    </p>
                </div>

                <!-- ─── REGISTER PANEL ─── -->
                <div x-show="authTab === 'register'" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="text-center mb-4" style="margin-bottom: 14px;">
                        <span class="text-xl font-bold text-brand tracking-tight font-sans inline-block">el Craft</span>
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
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#A98077] hover:text-brand transition-colors focus:outline-none"
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
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-[#A98077] hover:text-brand transition-colors focus:outline-none"
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
                            class="w-full py-2.5 bg-brand hover:bg-brandDark disabled:opacity-60 text-white font-semibold text-sm rounded-xl transition-colors duration-200 shadow-xs flex items-center justify-center gap-2">
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
                       class="w-full flex items-center justify-center gap-3 py-2.5 px-4 rounded-xl border border-[#E8B4AC] bg-white hover:bg-[#F5F0EE] text-brandDark font-semibold text-sm transition-colors duration-200 shadow-xs">
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
                        <button type="button" @click="switchAuthTab('login')" class="text-brand hover:text-brandDark font-semibold transition-colors">
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
    {{-- ── Floating WhatsApp Help Button ─────────────────────────────────── --}}
    @if(!empty($waHelpLink))
    <style>
        /* Floating WhatsApp FAB */
        #wa-fab-wrapper {
            position: fixed;
            bottom: 88px; /* above mobile dock (64px) + 24px gap */
            right: 20px;
            /* Tetap di atas konten halaman & dock (z-40), tapi DI BAWAH semua
               overlay modal (drawer/search/login: z-50 ke atas) agar tidak
               menghalangi login/register atau menu saat terbuka. */
            z-index: 45;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        @media (min-width: 768px) {
            #wa-fab-wrapper {
                bottom: 24px;
            }
        }
        #wa-fab-label {
            background: #1c1917;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            padding: 6px 12px;
            border-radius: 20px;
            white-space: nowrap;
            opacity: 0;
            transform: translateX(8px);
            transition: opacity 0.22s ease, transform 0.22s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.18);
        }
        #wa-fab-wrapper:hover #wa-fab-label {
            opacity: 1;
            transform: translateX(0);
        }
        #wa-fab-btn {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #C17B6F 0%, #9E5A4E 100%);
            box-shadow: 0 4px 16px rgba(193, 123, 111, 0.45);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            flex-shrink: 0;
        }
        #wa-fab-btn:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 22px rgba(193, 123, 111, 0.55);
        }
        /* Pulse ring */
        #wa-fab-btn::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: rgba(193, 123, 111, 0.35);
            animation: wa-fab-pulse 2.2s ease-out infinite;
        }
        @keyframes wa-fab-pulse {
            0%   { transform: scale(1);    opacity: 0.7; }
            70%  { transform: scale(1.45); opacity: 0;   }
            100% { transform: scale(1.45); opacity: 0;   }
        }
    </style>
    <div id="wa-fab-wrapper" x-cloak
         x-show="!mobileMenuOpen && !searchOpen && !loginModalOpen">
        <span id="wa-fab-label">Butuh bantuan? Chat kami di WhatsApp</span>
        <a id="wa-fab-btn"
           href="{{ $waHelpLink }}"
           target="_blank"
           rel="noopener noreferrer"
           aria-label="Chat WhatsApp Admin el Craft">
            {{-- WhatsApp icon (same path as returns/show.blade.php for consistency) --}}
            <svg width="26" height="26" viewBox="0 0 24 24" fill="white" aria-hidden="true">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
        </a>
    </div>
    @endif
    {{-- ── /Floating WhatsApp Help Button ──────────────────────────────────── --}}

    @stack('scripts')

</body>
</html>
