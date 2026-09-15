<?php

// Sample data and deliberately different PNG dimensions for the layout review.
// This runs only inside review-fixture.php's disposable SQLite database.
$sizes = ['portrait' => [400, 800], 'landscape' => [1000, 500], 'square' => [600, 600]];
mkdir($directory.'/images/products', 0777, true);
foreach ($sizes as $shape => [$width, $height]) {
    $canvas = imagecreatetruecolor($width, $height);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    $frame = imagecolorallocate($canvas, 30, 41, 59);
    $purple = imagecolorallocate($canvas, 67, 56, 202);
    $light = imagecolorallocate($canvas, 199, 210, 254);
    imagefill($canvas, 0, 0, $white);
    if ($shape === 'portrait') {
        imagefilledrectangle($canvas, 45, 20, 355, 780, $frame);
        imagefilledrectangle($canvas, 58, 46, 342, 750, $purple);
        imagefilledellipse($canvas, 200, 385, 250, 390, $light);
        imagefilledellipse($canvas, 200, 445, 230, 300, $purple);
        imagefilledrectangle($canvas, 155, 46, 245, 65, $frame);
    } elseif ($shape === 'landscape') {
        imagefilledrectangle($canvas, 125, 20, 875, 400, $frame);
        imagefilledrectangle($canvas, 142, 37, 858, 380, $purple);
        imagefilledellipse($canvas, 630, 205, 410, 280, $light);
        imagefilledellipse($canvas, 580, 255, 420, 300, $purple);
        imagefilledpolygon($canvas, [125, 405, 875, 405, 975, 480, 25, 480], $frame);
        imagefilledrectangle($canvas, 410, 422, 590, 450, $light);
    } else {
        imagefilledrectangle($canvas, 230, 15, 370, 585, $frame);
        imagefilledellipse($canvas, 300, 300, 365, 420, $frame);
        imagefilledellipse($canvas, 300, 300, 330, 385, $purple);
        imagesetthickness($canvas, 14);
        imagearc($canvas, 300, 300, 250, 280, 210, 510, $light);
        imageline($canvas, 300, 300, 300, 190, $white);
        imageline($canvas, 300, 300, 375, 330, $white);
    }
    imagepng($canvas, $directory.'/images/products/'.$shape.'.png');
    imagedestroy($canvas);
}
$laptops = App\Models\Category::create(['name' => 'Laptops']);
$watches = App\Models\Category::create(['name' => 'Smart Watches']);
$samples = [
    ['name' => 'Galaxy Ultra 256GB', 'brand' => 'Samsung', 'price' => 799, 'image_path' => 'products/portrait.png', 'category_id' => $category->id],
    ['name' => 'MacBook Air 13-inch with 16GB Memory', 'brand' => 'Apple', 'price' => 999, 'image_path' => 'products/landscape.png', 'category_id' => $laptops->id],
    ['name' => 'Watch Series 10', 'brand' => 'Apple', 'price' => 329, 'image_path' => 'products/square.png', 'category_id' => $watches->id],
    ['name' => 'Galaxy A56', 'brand' => 'Samsung', 'price' => 349, 'image_path' => 'products/portrait.png', 'category_id' => $category->id],
    ['name' => 'ThinkPad X1 Carbon Lightweight Business Laptop', 'brand' => 'Lenovo', 'price' => 1199, 'image_path' => 'products/landscape.png', 'category_id' => $laptops->id],
    ['name' => 'Watch SE', 'brand' => 'Apple', 'price' => 199, 'image_path' => 'products/square.png', 'category_id' => $watches->id],
    ['name' => 'Pixel 9 Pro', 'brand' => 'Google', 'price' => 699, 'image_path' => 'products/portrait.png', 'category_id' => $category->id],
    ['name' => 'Zenbook 14 OLED', 'brand' => 'Asus', 'price' => 899, 'image_path' => 'products/landscape.png', 'category_id' => $laptops->id],
];
foreach (App\Models\Product::orderByDesc('id')->get() as $index => $product) {
    $product->update($samples[$index % count($samples)]);
}
App\Models\Setting::first()->update(['store_name' => 'Mobile Hub', 'tagline' => 'Find your next device']);
