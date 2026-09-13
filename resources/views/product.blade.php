<x-layout>

    <div class="container" style="padding-top: 26px;">

        <div class="breadcrumb">
            <a href="{{ route('home') }}">Home</a>
            /
            <a href="{{ url('/search') }}">Products</a> 
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
                        📦
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


                @if (filled($product->description))
                    <p class="product-desc">{{ $product->description }}</p>
                @endif


                @if ($product->isPhone() && !empty($product->storage))

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


                @if ($product->isPhone() && !empty($product->ram))

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


                @php
                    $specifications = $product->specs ?? [];
                    if ($product->isPhone()) {
                        $specifications = collect(['Screen', 'Processor', 'Battery', 'Camera'])
                            ->mapWithKeys(fn ($name) => [$name => $specifications[$name] ?? $specifications[strtolower($name)] ?? null])
                            ->all();
                    }
                    $specifications = array_filter($specifications, fn ($value) => is_scalar($value) && trim((string) $value) !== '');
                @endphp
                @if ($specifications)
                    <table class="specs-table">
                        <caption>Specifications</caption>
                        @foreach ($specifications as $name => $value)
                            <tr><td>{{ $name }}</td><td>{{ $value }}</td></tr>
                        @endforeach
                    </table>
                @endif


            </div>

        </div>

    </div>




</x-layout>
