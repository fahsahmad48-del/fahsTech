<header class="site-header">

    <div class="header-inner">

        <a class="brand" href="{{ url('/') }}">

            <span class="brand-mark">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <rect
                        x="7"
                        y="2"
                        width="10"
                        height="20"
                        rx="2"
                        stroke="white"
                        stroke-width="2"
                    />

                    <line
                        x1="10"
                        y1="18"
                        x2="14"
                        y2="18"
                        stroke="white"
                        stroke-width="2"
                    />
                </svg>
            </span>

            <span id="storeNameLabel" title="{{ $storeSettings->store_name ?? 'Mobile Hub' }}">
                {{ $storeSettings->store_name ?? 'Mobile Hub' }}
            </span>

        </a>


        <button
            class="menu-toggle"
            id="menuToggle"
            type="button" aria-label="Menu" aria-controls="mainNav" aria-expanded="false"
        >
            <svg viewBox="0 0 24 24" fill="none">
                <path
                    d="M3 6h18M3 12h18M3 18h18"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                />
            </svg>
        </button>


        <nav class="main-nav" id="mainNav">

            <a
                href="{{ url('/') }}"
                class="{{ request()->is('/') ? 'active' : '' }}"
            >
                Home
            </a>


            <a
                href="{{ url('/search') }}"
                class="{{ request()->is('search') ? 'active' : '' }}"
            >
                Browse Phones
            </a>


            <a
                href="{{ url('/admin/products') }}"
                class="nav-cta {{ request()->is('admin*') ? 'active' : '' }}"
            >
                Admin
            </a>

        </nav>

    </div>

</header>
