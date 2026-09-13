
<x-layout>

    @if (trim((string) ($settings->offer_text ?? '')) !== '')
        <div class="container home-offer">
            <aside class="offer-bar" id="offerBar" aria-label="Store announcement">
                <button type="button" class="offer-icon" id="offerPause"
                    aria-label="Pause announcement animation" aria-pressed="false"
                    title="Pause announcement animation" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 9h4l11-5v16L8 15H4z" />
                        <path d="m8 15 2 6H6l-2-6M22 9v6" />
                    </svg>
                </button>
                <div class="offer-window">
                    <div class="offer-track">
                        <span class="offer-copy" id="offerText" dir="auto">{{ $settings->offer_text }}</span>
                        <span class="offer-copy" dir="auto" aria-hidden="true">{{ $settings->offer_text }}</span>
                    </div>
                </div>
                <div class="offer-arrows" aria-hidden="true">
                    <span>›</span><span>›</span><span>›</span>
                </div>
            </aside>
        </div>
        <script src="{{ asset('js/home.js') }}" defer></script>
    @endif

    <section class="hero">
        <div class="hero-inner">

            <div class="reveal reveal-left">
                <span class="hero-eyebrow">New arrivals every week</span>

                <h1>Find your next device, without the pressure.</h1>

                <p>
                    {{ $settings?->tagline ?? 'Browse our latest products and choose the one that suits you.' }}
                </p>

                <div class="hero-actions">
                    <a href="{{ url('/search') }}" class="btn btn-primary">
                        Browse Products
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
                        {{ $settings->about_text ?? 'We help you find the right device for your needs.' }}
                    </p>
                </div>

                <div class="about-points reveal reveal-right">

                    <div class="about-point">
                        <span class="about-point-icon">✅</span>

                        <div>
                            <strong>Hand-checked devices</strong>
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
                            <p>Feel free to contact us on Whatsapp</p>
                        </div>
                    </div>

                </div>

            </div>
        </section>


        <section class="store-services reveal" aria-labelledby="store-services-heading" >
            <div class="store-services-header">
                <div>
                    <span class="store-services-eyebrow">More Services</span>
                    <h2 id="store-services-heading" style="margin-top: 10px">Available in Store</h2>
                </div>

            </div>

            <div class="store-services-grid">
                <article class="store-service-card">
                    <div class="store-service-visual whish-visual" aria-hidden="true">
                        <svg viewBox="0 0 180 140" xmlns="http://www.w3.org/2000/svg" focusable="false">
                            <defs>
                                <linearGradient id="store-whish-red" x1="0" y1="0" x2="1" y2="1">
                                    <stop stop-color="#ef4444" />
                                    <stop offset=".55" stop-color="#dc2626" />
                                    <stop offset="1" stop-color="#b91c1c" />
                                </linearGradient>
                            </defs>
                            <ellipse cx="90" cy="120" rx="65" ry="8" fill="var(--text)" opacity=".04" />
                            <ellipse cx="90" cy="119" rx="51" ry="5" fill="var(--text)" opacity=".04" />
                            <g transform="rotate(-9 90 68)">
                                <rect x="16" y="24" width="148" height="94" rx="13" fill="var(--text)" opacity=".06" />
                                <rect x="16" y="18" width="148" height="94" rx="13" fill="url(#store-whish-red)" />
                                <path d="M108 18h43a13 13 0 0 1 13 13v48Z" fill="white" opacity=".08" />
                                <text x="30" y="49" fill="white" font-size="25" font-weight="700" font-family="inherit">whish</text>
                                <rect x="31" y="64" width="25" height="19" rx="4" fill="#fde68a" />
                                <path d="M39 64v19m9-19v19M31 73h25" fill="none" stroke="var(--accent-dark)" stroke-width="1" opacity=".6" />
                                <g fill="none" stroke="white" stroke-width="2" stroke-linecap="round">
                                    <path d="M134 62a11 11 0 0 1 0 16m5-20a17 17 0 0 1 0 24m-10-16a5 5 0 0 1 0 8" />
                                </g>
                                <path d="M31 96h43m9 0h20" stroke="white" stroke-width="3" stroke-linecap="round" opacity=".5" />
                            </g>
                        </svg>
                    </div>

                    <div class="store-service-content">
                        <h3>Whish Services</h3>
                        <p>Whish cards and related services available in store.</p>
                    </div>
                </article>

                <article class="store-service-card">
                    <div class="store-service-visual lotto-visual" aria-hidden="true">
                        <svg viewBox="0 0 180 140" xmlns="http://www.w3.org/2000/svg" focusable="false">
                            <ellipse cx="91" cy="126" rx="57" ry="7" fill="var(--text)" opacity=".04" />
                            <g transform="rotate(12 103 65)">
                                <rect x="58" y="12" width="91" height="105" rx="10" fill="var(--primary-light)" stroke="var(--primary)" stroke-opacity=".18" />
                                <path d="M72 31h62m-62 10h43m-43 59h62" stroke="var(--primary)" stroke-width="3" stroke-linecap="round" opacity=".2" />
                            </g>
                            <g transform="rotate(-9 79 76)">
                                <path d="M43 25h70a10 10 0 0 1 10 10v55a7 7 0 0 0 0 14v10a10 10 0 0 1-10 10H43a10 10 0 0 1-10-10v-10a7 7 0 0 0 0-14V35a10 10 0 0 1 10-10Z" fill="var(--primary)" />
                                <text x="46" y="48" fill="white" font-size="15" font-weight="800" letter-spacing="2" font-family="inherit">LOTTO</text>
                                <g fill="var(--primary-light)">
                                    <rect x="45" y="59" width="18" height="22" rx="4" />
                                    <rect x="69" y="59" width="18" height="22" rx="4" />
                                    <rect x="93" y="59" width="18" height="22" rx="4" />
                                </g>
                                <g fill="var(--primary-dark)" font-size="10" font-weight="700" text-anchor="middle" font-family="inherit">
                                    <text x="54" y="74">07</text>
                                    <text x="78" y="74">18</text>
                                    <text x="102" y="74">26</text>
                                </g>
                                <path d="M45 97h66" stroke="white" stroke-dasharray="3 4" opacity=".45" />
                                <path d="M53 110h50" stroke="white" stroke-width="3" stroke-linecap="round" opacity=".5" />
                            </g>
                            <path d="m145 74 4 8 9 1-7 6 2 9-8-5-8 5 2-9-7-6 9-1Z" fill="var(--primary)" />
                            <path d="m20 38-7-4m12-6-2-8m132 27 9-4m-9 13h11" fill="none" stroke="var(--primary)" stroke-width="3" stroke-linecap="round" opacity=".35" />
                        </svg>
                    </div>

                    <div class="store-service-content">
                        <h3>Lotto</h3>
                        <p>Lotto tickets available directly from our store.</p>
                    </div>
                </article>
            </div>
        </section>


        <section class="phone-care reveal" aria-labelledby="phone-care-heading">
            <div class="phone-care-inner">
                <div class="phone-care-content">
                    <span class="phone-care-badge">Phone Care</span>

                    <h2 id="phone-care-heading">We Maintain and Repair Phones</h2>

                    <p class="phone-care-description">
                        From screen replacement and battery issues to charging and software problems,
                        we help keep your phone working like new with reliable service and attention to detail.
                    </p>

                    <div class="phone-care-services">
                        <div class="phone-care-service">
                            <div class="phone-care-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <rect x="5" y="2" width="14" height="20" rx="3" />
                                    <path d="m13 2-3 7 5 3-4 6M10 19h4" />
                                </svg>
                            </div>
                            <div>
                                <h3>Screen repair</h3>
                                <p>Cracked or damaged screens</p>
                            </div>
                        </div>

                        <div class="phone-care-service">
                            <div class="phone-care-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <rect x="2" y="6" width="18" height="12" rx="2" />
                                    <path d="M22 10v4m-11-6-3 5h6l-3 4" />
                                </svg>
                            </div>
                            <div>
                                <h3>Battery replacement</h3>
                                <p>Get longer life from your phone</p>
                            </div>
                        </div>

                        <div class="phone-care-service">
                            <div class="phone-care-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <path d="M8 2v5m8-5v5M6 7h12v3a6 6 0 0 1-12 0V7Zm6 9v3a3 3 0 0 0 3 3h3" />
                                </svg>
                            </div>
                            <div>
                                <h3>Charging issues</h3>
                                <p>Fix charging problems</p>
                            </div>
                        </div>

                        <div class="phone-care-service">
                            <div class="phone-care-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                                    <rect x="2" y="3" width="20" height="18" rx="3" />
                                    <path d="M2 8h20m-14 4-3 3 3 3m8-6 3 3-3 3m-3-6-2 6" />
                                </svg>
                            </div>
                            <div>
                                <h3>Software support</h3>
                                <p>Resolve software issues</p>
                            </div>
                        </div>
                    </div>

                    <div class="phone-care-trust">
                        Fast <span aria-hidden="true">&bull;</span>
                        Reliable <span aria-hidden="true">&bull;</span>
                        Professional
                    </div>
                </div>

                <div class="phone-care-visual" aria-hidden="true">
                    <svg viewBox="0 0 360 360" xmlns="http://www.w3.org/2000/svg" fill="none" focusable="false">
                        <!-- Soft shapes and a grounding shadow. -->
                        <path class="phone-care-svg-soft" d="M49 112C69 61 136 35 203 49c67 14 118 66 115 131s-43 125-110 132C128 321 38 277 34 216c-3-42-2-62 15-104Z" />
                        <circle class="phone-care-svg-accent" cx="302" cy="80" r="12" opacity=".15" />
                        <circle class="phone-care-svg-accent" cx="45" cy="244" r="7" opacity=".2" />
                        <ellipse class="phone-care-svg-frame" cx="182" cy="321" rx="115" ry="11" opacity=".06" />

                        <!-- Phone frame and a simple restored-screen check. -->
                        <g transform="rotate(-8 167 177)">
                            <rect class="phone-care-svg-frame" x="101" y="53" width="132" height="251" rx="25" />
                            <rect class="phone-care-svg-surface" x="109" y="61" width="116" height="235" rx="19" />
                            <rect class="phone-care-svg-soft" x="118" y="88" width="98" height="175" rx="10" />
                            <rect class="phone-care-svg-frame" x="143" y="66" width="48" height="9" rx="4.5" />
                            <circle class="phone-care-svg-accent" cx="167" cy="166" r="30" />
                            <path d="m153 166 10 10 19-21" stroke="white" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                            <rect class="phone-care-svg-accent" x="138" y="217" width="58" height="6" rx="3" opacity=".2" />
                            <rect class="phone-care-svg-accent" x="149" y="230" width="36" height="5" rx="2.5" opacity=".12" />
                            <rect class="phone-care-svg-frame" x="150" y="280" width="34" height="4" rx="2" opacity=".3" />
                        </g>

                        <!-- Gray wrench, angled alongside the phone. -->
                        <g transform="rotate(32 267 200)">
                            <path class="phone-care-svg-muted" d="M256 163a26 26 0 0 1-10-43l3 25 15 7 15-11-2-25a26 26 0 0 1 2 46v98a12 12 0 0 1-24 0Z" />
                            <circle cx="267" cy="259" r="4" class="phone-care-svg-surface" />
                        </g>

                        <!-- Purple screwdriver with a steel shaft. -->
                        <g transform="rotate(-30 79 240)">
                            <path class="phone-care-svg-muted" d="M75 178v-37l4-10 4 10v37Z" />
                            <rect class="phone-care-svg-frame" x="70" y="176" width="18" height="9" rx="3" />
                            <rect class="phone-care-svg-accent" x="66" y="183" width="26" height="101" rx="11" />
                            <path d="M75 200v62m8-62v62" stroke="white" stroke-opacity=".4" stroke-width="3" stroke-linecap="round" />
                        </g>

                        <!-- Gear with eight teeth. -->
                        <g transform="translate(273 88)" class="phone-care-svg-accent">
                            <rect x="-7" y="-35" width="14" height="70" rx="3" />
                            <rect x="-7" y="-35" width="14" height="70" rx="3" transform="rotate(45)" />
                            <rect x="-7" y="-35" width="14" height="70" rx="3" transform="rotate(90)" />
                            <rect x="-7" y="-35" width="14" height="70" rx="3" transform="rotate(135)" />
                            <circle r="27" />
                            <circle r="15" class="phone-care-svg-surface" />
                            <circle r="7" class="phone-care-svg-soft" />
                        </g>
                    </svg>
                </div>
            </div>
        </section>


        <section class="section">

            <div class="section-head">
                <div>
                    <h2>Shop by category</h2>
                    <p>Choose a category and find matching products.</p>
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
                            {{ $category->products_count === 1 ? 'product' : 'products' }}
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
                    <p>The newest products added to our catalog.</p>
                </div>

                <a href="{{ url('/search') }}" class="link-more">
                    View all products →
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
                                    📦
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
