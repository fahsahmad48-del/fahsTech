<x-layout>
    <div class="container empty-state" role="alert">
        <h1>Too many attempts</h1>
        <p>Please wait {{ $exception->getHeaders()['Retry-After'] ?? 60 }} seconds before trying again.</p>
        <a class="btn btn-primary" href="{{ route('admin.login') }}">Back to admin</a>
    </div>
</x-layout>
