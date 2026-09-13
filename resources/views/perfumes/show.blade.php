<x-layout>
    <div class="container perfume-page">
        <div class="breadcrumb">
            <a href="{{ route('home') }}">Home</a> /
            <a href="{{ route('perfumes.index') }}">Perfumes</a> / {{ $perfume->name }}
        </div>
        <div class="product-layout">
            <div class="product-image reveal reveal-left">
                <img src="{{ asset('storage/'.$perfume->image_path) }}" alt="{{ $perfume->name }}">
            </div>
            <div class="product-info reveal reveal-right">
                <div class="brand-tag">{{ $perfume->brand }} &middot; {{ $perfume->categoryLabel() }}</div>
                <h1>{{ $perfume->name }}</h1>
                <div class="product-price">${{ number_format($perfume->price, 2) }}</div>
                @if (filled($perfume->description))
                    <p class="product-desc">{{ $perfume->description }}</p>
                @endif
                @php
                    $specifications = array_filter($perfume->specs ?? [], fn ($value) => is_scalar($value) && trim((string) $value) !== '');
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
