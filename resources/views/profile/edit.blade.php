<x-app-layout>
    <x-slot name="header">{{ __('Profile') }}</x-slot>

    <div class="space-y-6">
        <section class="mekuri px-6 py-6 sm:px-8 !font-sans !font-normal">
            <div class="max-w-xl">@include('profile.partials.update-profile-information-form')</div>
        </section>
        <section class="mekuri px-6 py-6 sm:px-8 !font-sans !font-normal">
            <div class="max-w-xl">@include('profile.partials.update-password-form')</div>
        </section>
        <section class="mekuri px-6 py-6 sm:px-8 !font-sans !font-normal">
            <div class="max-w-xl">@include('profile.partials.delete-user-form')</div>
        </section>
    </div>
</x-app-layout>
