@extends('layouts.storefront')

@section('title', 'Profil Saya | el Craft')
@section('meta_description', 'Kelola informasi profil, kata sandi, dan pengaturan akun Anda di el Craft.')

@section('content')
<div class="bg-white min-h-screen pb-24 md:pb-0">

    {{-- ─── HEADER ────────────────────────────────── --}}
    <x-page-header title="Profil Saya" />

    <section class="max-w-[1280px] mx-auto px-5 md:px-8 lg:px-16 py-8">
        <div class="max-w-3xl mx-auto space-y-6">
            {{-- Update Profile Information --}}
            <div class="bg-white border border-[#E8B4AC] rounded-card p-5 sm:p-6 shadow-xs">
                @include('profile.partials.update-profile-information-form')
            </div>

            {{-- Update Password --}}
            <div class="bg-white border border-[#E8B4AC] rounded-card p-5 sm:p-6 shadow-xs">
                @include('profile.partials.update-password-form')
            </div>

            {{-- Delete Account --}}
            <div class="bg-white border border-[#E24B4A] rounded-card p-5 sm:p-6 shadow-xs">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </section>
</div>
@endsection
