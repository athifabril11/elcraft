<!DOCTYPE html>
<html class="scroll-smooth" lang="id">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>@yield('title', 'el Craft | Timeless Elegance & Premium Accessories')</title>

    <!-- Google Fonts: Inter (font utama) + Material Symbols (ikon) -->
    <!-- Preconnect untuk mempercepat resolusi DNS ke server Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet"/>

    <!-- CSS & JS dikompilasi oleh Vite — token desain dari tailwind.config.js -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>[x-cloak] { display: none !important; }</style>

    @stack('styles')
</head>
<body x-data="storefrontLayout()" class="bg-white text-warmBlack font-sans antialiased selection:bg-accent selection:text-brandDark">

    <!-- 1. STICKY NAVIGATION BAR (White Background) -->
    <header class="sticky top-0 w-full z-50 bg-white border-b border-warmLightGrey">
        <div class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 h-20 flex items-center justify-between">
            <!-- Left: Logo -->
            <a href="/" class="text-[22px] font-semibold text-brand tracking-wide font-sans">el Craft</a>
            
            <!-- Center: Links (Desktop Only) -->
            <nav class="hidden md:flex space-x-8">
                <a href="/" class="{{ request()->is('/') ? 'text-brand' : 'text-warmBlack hover:text-brand' }} font-medium text-sm transition-colors duration-200 uppercase tracking-wider text-[11px]">Home</a>
                <a href="/products" class="{{ request()->is('products*') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} font-medium text-sm transition-colors duration-200 uppercase tracking-wider text-[11px]">Produk</a>
                <a href="/#categories" class="text-warmGrey hover:text-brand font-medium text-sm transition-colors duration-200 uppercase tracking-wider text-[11px]">Kategori</a>
                <a href="/#about" class="text-warmGrey hover:text-brand font-medium text-sm transition-colors duration-200 uppercase tracking-wider text-[11px]">Tentang</a>
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
                
                <!-- User Profile Icon (Desktop Only) -->
                <a href="/dashboard" class="hidden md:flex text-warmBlack hover:text-brand transition-colors duration-200 p-1.5 items-center justify-center" aria-label="User Profile">
                    <span class="material-symbols-outlined !text-[24px]">person</span>
                </a>
                
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
                    <a href="/#about" @click="toggleMobileMenu()" class="text-warmBlack hover:text-brand font-medium text-base border-b border-warmLightGrey/50 pb-2 transition-colors duration-200">Tentang</a>
                </nav>
            </div>
            
            @auth
                <div class="border-t border-warmLightGrey pt-6 flex flex-col space-y-3">
                    <a href="/dashboard" class="w-full text-center py-2.5 bg-brand text-white font-semibold text-sm rounded-btn hover:bg-brandDark transition-colors duration-200">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-center py-2.5 border border-brand text-brand font-semibold text-sm rounded-btn hover:bg-warmCream transition-colors duration-200">Keluar</button>
                    </form>
                </div>
            @else
                <div class="border-t border-warmLightGrey pt-6 flex flex-col space-y-3">
                    <a href="/login" class="w-full text-center py-2.5 border border-brand text-brand font-semibold text-sm rounded-btn hover:bg-warmCream transition-colors duration-200">Masuk</a>
                    <a href="/register" class="w-full text-center py-2.5 bg-brand text-white font-semibold text-sm rounded-btn hover:bg-brandDark transition-colors duration-200">Daftar</a>
                </div>
            @endauth
        </div>
    </div>

    <!-- Main Content Slot -->
    <main class="w-full">
        @yield('content')
    </main>

    <!-- 7. FOOTER SECTION (Warm Black Background) -->
    <footer id="about" class="bg-warmBlack text-white pt-16 pb-20 border-t border-warmGrey/20">
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
                    <li><a href="/#about" class="hover:text-white transition-colors duration-200">Tentang Kami</a></li>
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
    <div class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-warmLightGrey z-40 grid grid-cols-5 h-16 pb-safe">
        <a href="/" class="flex flex-col items-center justify-center {{ request()->is('/') ? 'text-brand' : 'text-warmGrey hover:text-brand' }} w-full h-full">
            <span class="material-symbols-outlined !text-[22px]">home</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Home</span>
        </a>
        <button @click="toggleSearch()" class="flex flex-col items-center justify-center text-warmGrey hover:text-brand w-full h-full">
            <span class="material-symbols-outlined !text-[22px]">search</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Search</span>
        </button>
        <a href="/cart" class="flex flex-col items-center justify-center text-warmGrey hover:text-brand relative w-full h-full">
            <span class="material-symbols-outlined !text-[22px]">shopping_bag</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Cart</span>
            <span class="absolute top-0.5 right-2 bg-brand text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300" x-show="cartCount > 0" x-text="cartCount" x-cloak>0</span>
        </a>
        <a href="/wishlist" class="flex flex-col items-center justify-center text-warmGrey hover:text-brand relative w-full h-full">
            <span class="material-symbols-outlined !text-[22px]">favorite</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Wishlist</span>
            <span class="absolute top-0.5 right-2 bg-brand text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center transition-all duration-300" x-show="wishlistCount > 0" x-text="wishlistCount" x-cloak>0</span>
        </a>
        <a href="/dashboard" class="flex flex-col items-center justify-center text-warmGrey hover:text-brand w-full h-full">
            <span class="material-symbols-outlined !text-[22px]">person</span>
            <span class="text-[10px] font-semibold tracking-wider mt-0.5">Profile</span>
        </a>
    </div>

    <!-- Login Prompt Modal -->
    <div id="login-modal" x-cloak class="fixed inset-0 z-[60] bg-warmBlack/40 backdrop-blur-xs flex items-center justify-center transition-all duration-300 opacity-0 pointer-events-none" :class="loginModalOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'">
        <div class="bg-white border border-warmLightGrey rounded-card max-w-sm w-full mx-5 p-6 shadow-md transition-all duration-300" :class="loginModalOpen ? 'scale-100' : 'scale-95'">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-warmBlack">Masuk ke Akun Anda</h3>
                <button @click="toggleLoginModal()" class="text-warmGrey hover:text-warmBlack flex items-center justify-center p-1" aria-label="Close modal">
                    <span class="material-symbols-outlined !text-[20px]">close</span>
                </button>
            </div>
            <p class="text-sm text-warmGrey mb-6 leading-relaxed">Untuk menyimpan produk ke wishlist atau menambahkan ke keranjang belanja, silakan masuk ke akun Anda atau daftar terlebih dahulu.</p>
            <div class="flex flex-col space-y-3">
                <a href="/login" class="w-full text-center py-2.5 bg-brand hover:bg-brandDark text-white font-semibold text-sm rounded-btn transition-colors duration-200">
                    Masuk
                </a>
                <a href="/register" class="w-full text-center py-2.5 border border-brand text-brand hover:bg-warmCream font-semibold text-sm rounded-btn transition-colors duration-200">
                    Daftar Akun Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Toast Notification Overlay Container -->
    <div id="toast-container" class="fixed top-24 right-5 z-[100] flex flex-col space-y-3 pointer-events-none"></div>

    <!-- Client-side Interactive Functionality Script -->
    <script>
        // Check if user is logged in
        const isUserLoggedIn = {{ auth()->check() ? 'true' : 'false' }};

        // 5. Toast Notification System
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

        // Subscriptions Form Handler (non-ajax welcome form)
        function handleSubscribe(e) {
            e.preventDefault();
            const input = e.target.querySelector('input');
            const email = input.value;
            showToast(`Terima kasih! ${email} telah bergabung dengan Inner Circle kami.`);
            input.value = '';
        }

        // Bridge functions for Vanilla JS files or legacy event handlers to proxy to Alpine
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

        window.toggleWishlistItem = (btnElement, productId, productName) => {
            if (window.Alpine) {
                const data = Alpine.$data(document.body);
                if (data && typeof data.toggleWishlistItem === 'function') {
                    data.toggleWishlistItem(productId, productName);
                }
            }
        };

        document.addEventListener('alpine:init', () => {
            Alpine.data('storefrontLayout', () => ({
                searchOpen: false,
                mobileMenuOpen: false,
                loginModalOpen: false,
                cartCount: 0,
                wishlistItems: [],
                wishlistCount: 0,

                init() {
                    this.wishlistItems = JSON.parse(localStorage.getItem('elcraft_wishlist_items') || '[]');
                    this.wishlistCount = this.wishlistItems.length;

                    // Sync changes across tabs
                    window.addEventListener('storage', () => {
                        this.cartCount = parseInt(localStorage.getItem('elcraft_cart_count') || '0', 10);
                        this.wishlistItems = JSON.parse(localStorage.getItem('elcraft_wishlist_items') || '[]');
                        this.wishlistCount = this.wishlistItems.length;
                    });

                    // Reset open modals/drawers when navigated back/forward via browser cache (bfcache)
                    window.addEventListener('pageshow', (event) => {
                        this.searchOpen = false;
                        this.mobileMenuOpen = false;
                        this.loginModalOpen = false;
                    });

                    // Sinkronisasi jumlah keranjang belanja dari database jika pengguna login
                    if (isUserLoggedIn) {
                        fetch('/cart/count')
                            .then(res => res.json())
                            .then(data => {
                                if (data && typeof data.count !== 'undefined') {
                                    this.cartCount = data.count;
                                    localStorage.setItem('elcraft_cart_count', data.count);
                                }
                            })
                            .catch(err => console.error('Gagal menyinkronkan keranjang belanja:', err));
                    } else {
                        this.cartCount = 0;
                        localStorage.setItem('elcraft_cart_count', '0');
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
                    if (!isUserLoggedIn) {
                        this.toggleLoginModal();
                        return;
                    }
                    
                    fetch('/cart/add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
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
                            showToast(data.message, 'success');
                        }
                    })
                    .catch(err => {
                        showToast(err.message || 'Gagal menambahkan produk.', 'error');
                    });
                },

                toggleWishlistItem(productId, productName) {
                    if (!isUserLoggedIn) {
                        this.toggleLoginModal();
                        return;
                    }
                    const index = this.wishlistItems.indexOf(productId);
                    if (index === -1) {
                        this.wishlistItems.push(productId);
                        showToast(`"${productName}" ditambahkan ke wishlist.`);
                    } else {
                        this.wishlistItems.splice(index, 1);
                        showToast(`"${productName}" dihapus dari wishlist.`, 'info');
                    }
                    localStorage.setItem('elcraft_wishlist_items', JSON.stringify(this.wishlistItems));
                    this.wishlistCount = this.wishlistItems.length;
                },

                isInWishlist(productId) {
                    return this.wishlistItems.includes(productId);
                }
            }));
        });
    </script>
    @stack('scripts')
</body>
</html>
