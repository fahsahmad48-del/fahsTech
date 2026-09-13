@extends('admin.layout')

@section('title', 'Store Settings')

@section('admin-content')
    <div id="adminMessage" class="note" role="status" aria-live="polite" hidden></div>

    <h2>Store Settings</h2>

    <p style="color: var(--muted);">
        Manage the information displayed on your store.
    </p>


    <form
        method="POST"
        id="settingsForm" action="{{ route('admin.settings.update') }}"
        style="max-width: 600px;"
    >

        @csrf
        @method('PUT')


        {{-- Store Name --}}
        <div class="field">

            <label for="store_name">
                Store Name
            </label>

            <input
                type="text"
                id="store_name" maxlength="100"
                name="store_name"
                value="{{ old('store_name', $settings?->store_name) }}"
                required
            >

        </div>


        {{-- Tagline --}}
        <div class="field">

            <label for="tagline">
                Tagline
            </label>

            <input
                type="text"
                id="tagline" maxlength="255"
                name="tagline"
                value="{{ old('tagline', $settings?->tagline) }}"
                placeholder="Find your next device"
            >

        </div>


        <div class="field">
            <label for="offer_text">Offer / Announcement Text</label>
            <textarea
                id="offer_text"
                name="offer_text"
                rows="3"
                maxlength="500"
                dir="auto"
                aria-describedby="offer_text_help"
            >{{ old('offer_text', $settings?->offer_text) }}</textarea>
            <small id="offer_text_help" style="color: var(--muted);">
                Up to 500 characters. Leave blank to hide the offer bar on the Home page.
            </small>
        </div>

        {{-- WhatsApp Number --}}
        <div class="field">
            <label for="whatsapp_number">WhatsApp Number</label>
            <input
                type="tel"
                id="whatsapp_number"
                name="whatsapp_number"
                maxlength="30"
                autocomplete="tel"
                value="{{ old('whatsapp_number', $settings?->whatsapp_number) }}"
                placeholder="e.g. +961 71 123 456"
                aria-describedby="whatsapp_number_help{{ $errors->has('whatsapp_number') ? ' whatsapp_number_error' : '' }}"
                @if ($errors->has('whatsapp_number')) aria-invalid="true" data-error-id="whatsapp_number_error" @endif
            >
            <small id="whatsapp_number_help" style="color: var(--muted);">
                Include the country code (7–15 digits). Spaces, +, dashes, dots and parentheses are allowed.
                Leave blank to hide the floating WhatsApp button.
            </small>
            @error('whatsapp_number')
                <small id="whatsapp_number_error" data-validation-error role="alert">{{ $message }}</small>
            @enderror
        </div>


        {{-- About Title --}}
        <div class="field">

            <label for="about_title">
                About Title
            </label>

            <input
                type="text"
                id="about_title" maxlength="150"
                name="about_title"
                value="{{ old('about_title', $settings?->about_title) }}"
                placeholder="About Mobile Hub"
            >

        </div>


        {{-- About Text --}}
        <div class="field">

            <label for="about_text">
                About Text
            </label>

            <textarea
                id="about_text" maxlength="3000"
                name="about_text"
                rows="6"
            >{{ old('about_text', $settings?->about_text) }}</textarea>

        </div>


        <button type="submit" class="btn btn-primary">
            Save Settings
        </button>

    </form>

    <hr style="margin: 40px 0; border: 0; border-top: 1px solid var(--border);">

    <h2>Change Admin Password</h2>

    <p style="color: var(--muted);">
        Enter your current password before choosing a new one.
    </p>

    <form
        method="POST"
        id="passwordForm" action="{{ route('admin.settings.password') }}"
        style="max-width: 600px;"
    >
        @csrf
        @method('PUT')


        <div class="field">
            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password" maxlength="72"
                name="current_password"
                required
                autocomplete="current-password"
            >
        </div>


        <div class="field">
            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password" maxlength="72"
                name="new_password"
                required
                minlength="8"
                autocomplete="new-password"
            >
        </div>


        <div class="field">
            <label for="new_password_confirmation">
                Confirm New Password
            </label>

            <input
                type="password"
                id="new_password_confirmation" maxlength="72"
                name="new_password_confirmation"
                required
                minlength="8"
                autocomplete="new-password"
            >
        </div>


        <button type="submit" class="btn btn-primary">
            Change Password
        </button>

    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/common.js') }}" defer></script>
    <script src="{{ asset('js/admin/settings.js') }}" defer></script>
@endpush
