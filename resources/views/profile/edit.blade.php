@extends('layouts.storefront')

@section('title', 'Profil Saya | el Craft')
@section('meta_description', 'Kelola informasi profil, kata sandi, dan pengaturan akun Anda di el Craft.')

@section('content')
<div class="bg-white min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <x-page-header title="Profil Saya" />

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-10">
        <div class="max-w-3xl mx-auto space-y-8">
            {{-- Update Profile Information --}}
            <div class="p-6 sm:p-8 bg-white border border-warmLightGrey rounded-card shadow-xs">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- Update Password --}}
            <div class="p-6 sm:p-8 bg-white border border-warmLightGrey rounded-card shadow-xs">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Delete Account --}}
            <div class="p-6 sm:p-8 bg-white border border-warmLightGrey rounded-card shadow-xs">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
