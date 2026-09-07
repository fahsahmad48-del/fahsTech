<x-layout>

    <div class="admin-login">

        <h2>Admin Login</h2>

        <p style="color: var(--muted); font-size: .88rem;">
            Store owner access only.
        </p>

        {{-- Errors --}}
        @if ($errors->any())
            <div
                class="note"
                style="
                    background: #fef2f2;
                    border-color: #fecaca;
                    color: #dc2626;
                "
            >
                {{ $errors->first() }}
            </div>
        @endif


        <form
            method="POST"
            action="{{ route('admin.login.submit') }}"
        >
            @csrf

            <div class="field">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter admin password"
                    required
                    autofocus
                    autocomplete="current-password"
                >

            </div>

            <button
                type="submit"
                class="btn btn-call btn-block"
            >
                Login
            </button>

        </form>

    </div>

</x-layout>
