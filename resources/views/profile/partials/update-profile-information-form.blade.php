<section>
    <header class="mb-5">
        <h2 class="text-sm font-semibold text-brandDark">
            Informasi Profil
        </h2>
        <p class="text-xs text-[#A98077] mt-1">
            Perbarui nama dan alamat email akun Anda.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
        @csrf
        @method('patch')

        {{-- Name --}}
        <div>
            <label for="name" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Nama Lengkap
            </label>
            <div style="position:relative;">
                <span style="position:absolute; top:50%; left:12px; transform:translateY(-50%); display:flex; align-items:center; pointer-events:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C8A09A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $user->name) }}"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Nama Anda"
                    style="width:100%;padding:10px 14px 10px 36px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;"
                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                >
            </div>
            @error('name')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Alamat Email
            </label>
            <div style="position:relative;">
                <span style="position:absolute; top:50%; left:12px; transform:translateY(-50%); display:flex; align-items:center; pointer-events:none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#C8A09A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <polyline points="2,4 12,13 22,4"/>
                    </svg>
                </span>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $user->email) }}"
                    required
                    autocomplete="username"
                    placeholder="nama@email.com"
                    style="width:100%;padding:10px 14px 10px 36px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;"
                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                >
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-3 p-3 bg-[#F5F0EE] border border-[#E8B4AC] rounded-xl text-xs" style="color:#5F5E5A;">
                    <p>
                        Alamat email Anda belum terverifikasi.
                        <button form="send-verification" class="underline font-semibold ml-1 transition-colors cursor-pointer" style="color:#C17B6F;" onmouseenter="this.style.color='#9E5A4E'" onmouseleave="this.style.color='#C17B6F'">
                            Klik di sini untuk mengirim ulang email verifikasi.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-xs text-emerald-600">
                            Tautan verifikasi baru telah dikirimkan ke alamat email Anda.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Submit & Status --}}
        <div class="flex items-center gap-4 pt-2">
            <button
                type="submit"
                style="padding:10px 20px;background:#C17B6F;color:#fff;font-weight:600;font-size:14px;border:none;border-radius:12px;cursor:pointer;transition:background .2s;"
                onmouseenter="this.style.background='#9E5A4E'"
                onmouseleave="this.style.background='#C17B6F'">
                Simpan Perubahan
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-xs text-emerald-600 font-medium"
                >
                    Perubahan profil berhasil disimpan.
                </p>
            @endif
        </div>
    </form>
</section>
