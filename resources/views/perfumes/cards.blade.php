@forelse ($perfumes as $perfume)
    <a class="card reveal" href="{{ route('perfumes.show', ['perfume' => $perfume->slug]) }}">
        <div class="card-image"><img src="{{ asset('storage/'.$perfume->image_path) }}" alt="{{ $perfume->name }}" loading="lazy"></div>
        <div class="card-body">
            <div class="card-brand">{{ $perfume->brand }}</div>
            <div class="card-name">{{ $perfume->name }}</div>
            <div class="perfume-category">{{ $perfume->categoryLabel() }}</div>
            <div class="card-price">${{ number_format($perfume->price, 2) }}</div>
        </div>
    </a>
@empty
    <p class="perfume-empty">No perfumes found in this category.</p>
@endforelse
