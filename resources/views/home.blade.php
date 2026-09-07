
<x-layout>

    <section class="hero">
        <div class="hero-inner">

            <div class="reveal reveal-left">
                <span class="hero-eyebrow">New arrivals every week</span>

                <h1>Find your next phone, without the pressure.</h1>

                <p>
                    {{ $settings?->tagline ?? 'Browse our latest phones and choose the one that suits you.' }}
                </p>

                <div class="hero-actions">
                    <a href="{{ url('/search') }}" class="btn btn-primary">
                        Browse Phones
                    </a>

                    <a href="#latest" class="btn btn-light">
                        See Latest Arrivals
                    </a>
                </div>
            </div>

            <div class="hero-art reveal reveal-right">
            <svg viewBox="0 0 300 320" xmlns="http://www.w3.org/2000/svg">
                <rect x="70" y="10" width="160" height="300" rx="26" fill="rgba(255,255,255,.14)" stroke="rgba(255,255,255,.5)" stroke-width="3"/>
                <rect x="90" y="40" width="120" height="200" rx="10" fill="rgba(255,255,255,.22)"/>
                <circle cx="150" cy="270" r="12" fill="rgba(255,255,255,.5)"/>
                <rect x="102" y="55" width="45" height="45" rx="9" fill="#fbbf24"/>
                <rect x="153" y="55" width="45" height="45" rx="9" fill="rgba(255,255,255,.55)"/>
                <rect x="102" y="106" width="45" height="45" rx="9" fill="rgba(255,255,255,.55)"/>
                <rect x="153" y="106" width="45" height="45" rx="9" fill="rgba(255,255,255,.35)"/>
                <rect x="102" y="157" width="96" height="30" rx="8" fill="rgba(255,255,255,.35)"/>
            </svg>
            </div>

        </div>
    </section>


    <div class="container">

        <section class="section about-section">
            <div class="about-layout">

                <div class="reveal reveal-left">
                    <h2>
                        {{ $settings->about_title ?? 'About our store' }}
                    </h2>

                    <p class="about-text">
                        {{ $settings->about_text ?? 'We help you find the right phone for your needs.' }}
                    </p>
                </div>

                <div class="about-points reveal reveal-right">

                    <div class="about-point">
                        <span class="about-point-icon">✅</span>

                        <div>
                            <strong>Hand-checked phones</strong>
                            <p>Every device is checked before it goes on the shelf.</p>
                        </div>
                    </div>

                    <div class="about-point">
                        <span class="about-point-icon">🤝</span>

                        <div>
                            <strong>No pressure</strong>
                            <p>Browse freely and contact us when you're ready.</p>
                        </div>
                    </div>

                    <div class="about-point">
                        <span class="about-point-icon">💬</span>

                        <div>
                            <strong>Easy to reach</strong>
                            <p>Call or WhatsApp us directly from any product page.</p>
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <section class="section">

            <div class="section-head">
                <div>
                    <h2>Shop by category</h2>
                    <p>Choose a category and find matching phones.</p>
                </div>
            </div>

            <div class="cat-grid">

                @forelse ($categories as $category)

                    <a
                        href="{{ url('/search?category=' . $category->id) }}"
                        class="cat-tile reveal"
                    >
                        <div class="cat-name">
                            {{ $category->name }}
                        </div>

                        <div class="cat-count">
                            {{ $category->products_count }}
                            {{ $category->products_count === 1 ? 'phone' : 'phones' }}
                        </div>
                    </a>

                @empty

                    <p>No categories available.</p>

                @endforelse

            </div>

        </section>


        <section class="section" id="latest">

            <div class="section-head">

                <div>
                    <h2>Latest arrivals</h2>
                    <p>The newest phones added to our catalog.</p>
                </div>

                <a href="{{ url('/search') }}" class="link-more">
                    View all phones →
                </a>

            </div>

            <div class="grid">

                @forelse ($products as $product)

                    <a
                        href="{{ url('/products/' . $product->slug) }}"
                        class="card reveal"
                    >

                        <div class="card-image">

                            @if ($product->image_path)

                                <img
                                    src="{{ asset('storage/' . $product->image_path) }}"
                                    alt="{{ $product->name }}"
                                >

                            @else

                                <div class="product-placeholder">
                                    📱
                                </div>

                            @endif

                        </div>

                        <div class="card-body">

                            <div class="card-brand">
                                {{ $product->brand }}
                            </div>

                            <div class="card-name">
                                {{ $product->name }}
                            </div>

                            <div class="card-price">
                                ${{ number_format($product->price, 2) }}
                            </div>

                        </div>

                    </a>

                @empty

                    <p>No products available yet.</p>

                @endforelse

            </div>

        </section>

    </div>




</x-layout>

