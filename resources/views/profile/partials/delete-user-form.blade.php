<section class="space-y-4">
    <header>
        <h2 class="text-sm font-semibold text-[#A32D2D]">
            Hapus Akun
        </h2>
        <p class="text-xs text-[#A98077] mt-1">
            Setelah akun dihapus, semua data akan hilang permanen. Pastikan Anda sudah yakin.
        </p>
    </header>

    <div>
        <button
            type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="w-full py-2.5 font-semibold text-sm rounded-xl transition-colors duration-200 inline-flex items-center justify-center gap-1.5"
            style="background:#E24B4A;color:#fff;border:none;cursor:pointer;"
            onmouseenter="this.style.background='#A32D2D'"
            onmouseleave="this.style.background='#E24B4A'"
        >
            <span class="material-symbols-outlined !text-[16px]">delete</span>
            <span>Hapus Akun Saya</span>
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" maxWidth="md" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6" x-data="{ confirmText: '' }">
            @csrf
            @method('delete')

            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" style="background-color: #FEE2E2; color: #DC2626;">
                    <span class="material-symbols-outlined !text-[22px]" style="color: #DC2626;">warning</span>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-warmBlack">
                        Apakah Anda yakin ingin menghapus akun?
                    </h2>
                </div>
            </div>

            <p class="text-xs text-[#5F5E5A] leading-relaxed mb-5">
                @if($user->google_id !== null)
                    Setelah akun Anda dihapus, semua data dan riwayat pesanan akan dihapus secara permanen. Ketik <strong class="text-warmBlack">HAPUS</strong> untuk mengonfirmasi bahwa Anda benar-benar ingin menghapus akun ini.
                @else
                    Setelah akun Anda dihapus, semua data dan riwayat pesanan akan dihapus secara permanen. Masukkan kata sandi Anda untuk mengonfirmasi bahwa Anda benar-benar ingin menghapus akun ini.
                @endif
            </p>

            @if($user->google_id !== null)
                {{-- Google Account: Text confirmation --}}
                <div class="mb-5">
                    <label for="delete_account_confirmation" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                        Ketik <strong class="text-[#DC2626]">HAPUS</strong> untuk konfirmasi
                    </label>

                    <input
                        id="delete_account_confirmation"
                        name="confirmation"
                        type="text"
                        x-model="confirmText"
                        placeholder="HAPUS"
                        autocomplete="off"
                        style="width:100%;padding:10px 14px;font-size:14px;color:#9E5A4E;border:1px solid #E8B4AC;border-radius:12px;background:#fff;outline:none;transition:border-color .2s,box-shadow .2s;"
                        onfocus="this.style.borderColor='#C17B6F';this.style.boxShadow='0 0 0 3px rgba(193,123,111,0.15)';"
                        onblur="this.style.borderColor='#E8B4AC';this.style.boxShadow='none';"
                    >

                    @if($errors->userDeletion->get('confirmation'))
                        <p class="mt-1.5 text-xs text-red-500">{{ $errors->userDeletion->first('confirmation') }}</p>
                    @endif
                </div>
            @else
                {{-- Password Account: Password confirmation --}}
                <div class="mb-5">
                    <label for="delete_account_password" class="block text-xs font-medium mb-1.5" style="color:#5F5E5A;">
                        Kata Sandi
                    </label>

                    <div class="relative" x-data="{ showPassword: false }">
                        <input
                            id="delete_account_password"
                            name="password"
                            :type="showPassword ? 'text' : 'password'"
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

                    @if($errors->userDeletion->get('password'))
                        <p class="mt-1.5 text-xs text-red-500">{{ $errors->userDeletion->first('password') }}</p>
                    @endif
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 pt-2">
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="border border-warmLightGrey bg-white text-[#5F5E5A] hover:bg-warmCream font-medium text-xs transition-colors cursor-pointer"
                    style="padding:10px 18px;border-radius:12px;"
                >
                    Batal
                </button>

                @if($user->google_id !== null)
                    <button
                        type="submit"
                        :disabled="confirmText !== 'HAPUS'"
                        :class="confirmText !== 'HAPUS' ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
                        class="font-semibold text-xs transition-all shadow-xs inline-flex items-center gap-1.5"
                        style="background-color:#E24B4A;color:#ffffff;padding:10px 20px;border-radius:12px;border:none;"
                        onmouseenter="if (!this.disabled) this.style.backgroundColor='#A32D2D'"
                        onmouseleave="this.style.backgroundColor='#E24B4A'"
                    >
                        <span class="material-symbols-outlined !text-[16px]">delete</span>
                        <span>Hapus Akun</span>
                    </button>
                @else
                    <button
                        type="submit"
                        class="font-semibold text-xs transition-all shadow-xs cursor-pointer inline-flex items-center gap-1.5"
                        style="background-color:#E24B4A;color:#ffffff;padding:10px 20px;border-radius:12px;border:none;"
                        onmouseenter="this.style.backgroundColor='#A32D2D'"
                        onmouseleave="this.style.backgroundColor='#E24B4A'"
                    >
                        <span class="material-symbols-outlined !text-[16px]">delete</span>
                        <span>Hapus Akun</span>
                    </button>
                @endif
            </div>
        </form>
    </x-modal>
</section>
