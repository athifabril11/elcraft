<section>
    <header class="mb-5">
        <h2 class="text-sm font-semibold text-brandDark">
            Ubah Kata Sandi
        </h2>
        <p class="text-xs text-[#A98077] mt-1">
            Gunakan kata sandi yang kuat dan belum pernah dipakai.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('put')

        {{-- Current Password --}}
        <div>
            <label for="update_password_current_password" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Kata Sandi Saat Ini
            </label>
            <div class="relative" x-data="{ showPassword: false }">
                <input
                    id="update_password_current_password"
                    name="current_password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    placeholder="••••••••"
                    style="width:100%;padding:10px 44px 10px 14px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;"
                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                >
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    tabindex="-1"
                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    style="position:absolute;top:0;right:0;bottom:0;width:44px;display:flex;align-items:center;justify-content:center;color:#A98077;background:none;border:none;cursor:pointer;padding:0;"
                    onmouseenter="this.style.color='#C17B6F'"
                    onmouseleave="this.style.color='#A98077'"
                >
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
            @if($errors->updatePassword->get('current_password'))
                <p class="mt-1.5 text-xs text-red-500">{{ $errors->updatePassword->first('current_password') }}</p>
            @endif
        </div>

        {{-- New Password --}}
        <div>
            <label for="update_password_password" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Kata Sandi Baru
            </label>
            <div class="relative" x-data="{ showPassword: false }">
                <input
                    id="update_password_password"
                    name="password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    style="width:100%;padding:10px 44px 10px 14px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;"
                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                >
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    tabindex="-1"
                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    style="position:absolute;top:0;right:0;bottom:0;width:44px;display:flex;align-items:center;justify-content:center;color:#A98077;background:none;border:none;cursor:pointer;padding:0;"
                    onmouseenter="this.style.color='#C17B6F'"
                    onmouseleave="this.style.color='#A98077'"
                >
                    <svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
            @if($errors->updatePassword->get('password'))
                <p class="mt-1.5 text-xs text-red-500">{{ $errors->updatePassword->first('password') }}</p>
            @endif
        </div>

        {{-- Confirm Password --}}
        <div>
            <label for="update_password_password_confirmation" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                Konfirmasi Kata Sandi
            </label>
            <div class="relative" x-data="{ showPassword: false }">
                <input
                    id="update_password_password_confirmation"
                    name="password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    style="width:100%;padding:10px 44px 10px 14px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;"
                    onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                    onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                >
                <button
                    type="button"
                    @click="showPassword = !showPassword"
                    tabindex="-1"
                    :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                    style="position:absolute;top:0;right:0;bottom:0;width:44px;display:flex;align-items:center;justify-content:center;color:#A98077;background:none;border:none;cursor:pointer;padding:0;"
                    onmouseenter="this.style.color='#C17B6F'"
                    onmouseleave="this.style.color='#A98077'"
                >
                    <svg x-show="!showPassword" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg x-show="showPassword" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
            @if($errors->updatePassword->get('password_confirmation'))
                <p class="mt-1.5 text-xs text-red-500">{{ $errors->updatePassword->first('password_confirmation') }}</p>
            @endif
        </div>

        {{-- Submit & Status --}}
        <div class="flex items-center gap-4 pt-2">
            <button
                type="submit"
                style="padding:10px 20px;background:#C17B6F;color:#fff;font-weight:600;font-size:14px;border:none;border-radius:12px;cursor:pointer;transition:background .2s;"
                onmouseenter="this.style.background='#9E5A4E'"
                onmouseleave="this.style.background='#C17B6F'">
                Perbarui Kata Sandi
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2500)"
                    class="text-xs text-emerald-600 font-medium"
                >
                    Kata sandi berhasil diperbarui.
                </p>
            @endif
        </div>
    </form>
</section>
