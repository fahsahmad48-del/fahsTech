<x-layout>

    <div class="container" style="padding-top: 26px;">

        <div class="breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            /
            <a href="{{ url('/search') }}">Phones</a>
            /
            {{ $product->brand }} {{ $product->name }}
        </div>


        <div class="product-layout">

            <div class="product-image reveal reveal-left">

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


            <div class="product-info reveal reveal-right">

                <div class="brand-tag">
                    {{ $product->brand }}
                    ·
                    {{ $product->category?->name ?? 'Uncategorized' }}
                </div>


                <h1>
                    {{ $product->name }}
                </h1>


                <div class="product-price">
                    ${{ number_format($product->price, 2) }}
                </div>


                <p class="product-desc">
                    {{ $product->description }}
                </p>


                @if (!empty($product->storage))

                    <div class="option-row">

                        <span class="label">
                            Storage options
                        </span>

                        <div class="pill-group">

                            @foreach ($product->storage as $storage)

                                <span class="pill">
                                    {{ $storage }}
                                </span>

                            @endforeach

                        </div>

                    </div>

                @endif


                @if (!empty($product->ram))

                    <div class="option-row">

                        <span class="label">
                            RAM options
                        </span>

                        <div class="pill-group">

                            @foreach ($product->ram as $ram)

                                <span class="pill">
                                    {{ $ram }}
                                </span>

                            @endforeach

                        </div>

                    </div>

                @endif


                @if (!empty($product->colors))

                    <div class="option-row">

                        <span class="label">
                            Available colors
                        </span>

                        <div class="pill-group">

                            @foreach ($product->colors as $color)

                                <span class="pill">
                                    {{ $color }}
                                </span>

                            @endforeach

                        </div>

                    </div>

                @endif


                <table class="specs-table">

                    <tr>
                        <td>Screen</td>

                        <td>
                            {{ $product->specs['Screen'] ?? $product->specs['screen'] ?? '—' }}
                        </td>
                    </tr>

                    <tr>
                        <td>Processor</td>

                        <td>
                            {{ $product->specs['Processor'] ?? $product->specs['processor'] ?? '—' }}
                        </td>
                    </tr>

                    <tr>
                        <td>Battery</td>

                        <td>
                            {{ $product->specs['Battery'] ?? $product->specs['battery'] ?? '—' }}
                        </td>
                    </tr>

                    <tr>
                        <td>Camera</td>

                        <td>
                            {{ $product->specs['Camera'] ?? $product->specs['camera'] ?? '—' }}
                        </td>
                    </tr>

                </table>


                <div class="contact-row">

                    @if (!empty($settings->whatsapp))

                        <a
                            class="btn btn-whatsapp"
                            href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->whatsapp) }}"
                            target="_blank"
                            rel="noopener"
                        >
                            WhatsApp us
                        </a>

                    @endif

                </div>

            </div>

        </div>

    </div>




</x-layout>
