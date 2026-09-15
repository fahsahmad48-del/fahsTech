<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title', 'Admin') — {{ $storeSettings->store_name ?? 'Mobile Hub' }}</title>

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="{{ asset('js/store.js') }}" defer></script>
</head>

<body>

    <header class="site-header">
        <div class="header-inner">

            <a href="{{ route('home') }}" class="brand">
                <span class="brand-mark">M</span>
                <span title="{{ $storeSettings->store_name ?? 'Mobile Hub' }}">{{ $storeSettings->store_name ?? 'Mobile Hub' }}</span>
            </a>

            <button type="button" class="menu-toggle" id="menuToggle" aria-label="Menu" aria-controls="mainNav" aria-expanded="false">
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
                <a href="{{ route('home') }}">Home</a>
                <a href="{{ route('search') }}">Browse Products</a>
                <a href="{{ route('perfumes.index') }}">Perfumes</a>
                <a href="{{ url('/admin/products') }}" class="active">Admin</a>
            </nav>

        </div>
    </header>


    <main class="container" style="padding-top: 30px;">

        {{-- Admin title + logout --}}
        <div class="admin-bar">

            <h1 style="margin: 0; font-size: 1.5rem;">
                Admin Dashboard
            </h1>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf

                <button type="submit" class="btn btn-outline btn-sm">
                    Logout
                </button>
            </form>

        </div>


        {{-- Admin tabs --}}
        <div class="admin-tabs">

            <a
                href="{{ url('/admin/products') }}"
                class="admin-tab {{ request()->is('admin/products*') ? 'active' : '' }}"
            >
                Products
            </a>

            <a
                href="{{ url('/admin/categories') }}"
                class="admin-tab {{ request()->is('admin/categories*') ? 'active' : '' }}"
            >
                Categories
            </a>

            <a href="{{ route('admin.perfumes.index') }}" class="admin-tab {{ request()->routeIs('admin.perfumes.*') ? 'active' : '' }}">
                Perfumes
            </a>

            <a
                href="{{ url('/admin/settings') }}"
                class="admin-tab {{ request()->is('admin/settings*') ? 'active' : '' }}"
            >
                Store Settings
            </a>

        </div>


        {{-- Success message --}}
        @section('admin-messages')
        @if (session('success'))
            <div class="note note-success" role="status">
                {{ session('success') }}
            </div>
        @endif


        {{-- Validation errors --}}
        @if ($errors->any())
            <div
                class="note" role="alert"
                style="background:#fef2f2; border-color:#fecaca; color:#dc2626;"
            >
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif


        @show

        {{-- Page content --}}
        @yield('admin-content')

    </main>

    @stack('scripts')

</body>

</html>
