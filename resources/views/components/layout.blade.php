
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $storeSettings->store_name ?? 'Mobile Hub' }} — Phone Store</title>


    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="{{ asset('js/store.js') }}" defer></script>
</head>

@php
    $whatsappNumber = request()->routeIs('home', 'search', 'products.show')
        ? \App\Support\WhatsAppNumber::normalize($storeSettings->whatsapp_number ?? null)
        : null;
@endphp

<body @class(['has-whatsapp-contact' => $whatsappNumber !== null])>

    <x-navbar />

    <main>
        {{ $slot }}
    </main>

    <x-footer />

    @if ($whatsappNumber !== null)
        <x-whatsapp-button :number="$whatsappNumber" />
    @endif



</body>

</html>
