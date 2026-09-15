<?php

// Used only by review-flows.mjs; all state lives in its disposable directory.
$root = dirname(__DIR__, 2);
$directory = getenv('MINISTORE_REVIEW_DIR');
$testing = realpath($root.'/storage/framework/testing');
if (! $directory || ! $testing || realpath(dirname($directory)) !== $testing
    || ! str_starts_with(basename($directory), 'browser-review-')) {
    throw new RuntimeException('An isolated browser review directory is required.');
}
foreach ([
    'APP_ENV' => 'local', 'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('r', 32)),
    'APP_URL' => 'http://127.0.0.1:'.getenv('MINISTORE_REVIEW_PORT'),
    'APP_CONFIG_CACHE' => $directory.'/config.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite', 'DB_URL' => '',
    'CACHE_STORE' => 'database', 'DB_CACHE_CONNECTION' => 'sqlite',
    'SESSION_DRIVER' => 'database', 'SESSION_CONNECTION' => 'sqlite',
    'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '',
    'SESSION_COOKIE' => 'ministore_review', 'LOG_CHANNEL' => 'single',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['filesystems.disks.public.root' => $directory.'/images', 'logging.channels.single.path' => $directory.'/laravel.log']);

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === '--seed') {
    if (file_exists($directory.'/database.sqlite')) {
        throw new RuntimeException('Refusing to seed an existing database.');
    }
    touch($directory.'/database.sqlite');
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    App\Models\Admin::create(['password' => 'review-password']);
    App\Models\Setting::create(['store_name' => 'Review Store', 'tagline' => 'Local browser tests']);
    $category = App\Models\Category::create(['name' => 'Phones']);
    App\Models\Category::create(['name' => 'Empty category']);
    $image = imagecreatetruecolor(1600, 800);
    imagepng($image, $directory.'/upload.png');
    imagedestroy($image);
    for ($i = 1; $i <= 17; $i++) {
        App\Models\Product::create(['name' => 'Review phone '.$i, 'brand' => 'Review', 'price' => 123.45, 'category_id' => $category->id]);
    }
    for ($i = 1; $i <= 17; $i++) {
        App\Models\Perfume::create(['name' => 'Review perfume '.$i, 'category' => $i % 2 ? 'men' : 'women', 'price' => 20, 'image_path' => '']);
    }
    if (getenv('MINISTORE_CARD_REVIEW') === '1') {
        require __DIR__.'/card-fixtures.php';
    }
    echo "Isolated SQLite database seeded.\n";
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/storage/')) {
    $file = realpath($directory.'/images/'.substr($path, 9));
    $images = realpath($directory.'/images');
    if ($file && $images && str_starts_with($file, $images.DIRECTORY_SEPARATOR) && is_file($file)) {
        header('Content-Type: '.mime_content_type($file));
        readfile($file);
    } else {
        http_response_code(404);
    }
    return;
}
// Serve only the project's public assets through the built-in server.
if (preg_match('~^/(css|js)/[a-zA-Z0-9/._-]+$~', $path) && is_file($root.'/public'.$path)) {
    return false;
}
$app->handleRequest(Illuminate\Http\Request::capture());
