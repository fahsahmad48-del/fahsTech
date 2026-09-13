<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;

class CatalogImageService
{
    public function save(UploadedFile $file, string $directory): string
    {
        $path = $directory.'/'.Str::uuid().'.webp';
        try {
            $manager = new ImageManager(config('intervention-image.driver'), decodeAnimation: false);
            $image = $manager->decodePath($file->getRealPath());
        } catch (DecoderException $exception) {
            throw ValidationException::withMessages(['image' => 'This image could not be read. Please choose another image.']);
        }
        $image->scaleDown(width: 1200, height: 1200);
        $saved = Storage::disk('public')->put(
            $path,
            (string) $image->encodeUsingFileExtension('webp', quality: 75, strip: true)
        );
        if (! $saved) {
            throw ValidationException::withMessages(['image' => 'The image could not be saved. Please try again.']);
        }

        return $path;
    }
}
