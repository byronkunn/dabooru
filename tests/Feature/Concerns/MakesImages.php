<?php

namespace Tests\Feature\Concerns;

use Illuminate\Http\UploadedFile;
use Tests\Support\FakeImage;

trait MakesImages
{
    protected function pngUpload(string $name = 'image.png', int $width = 10, int $height = 10, ?string $clientName = null, string $mime = 'image/png'): UploadedFile
    {
        $file = FakeImage::upload($name, $width, $height);

        if ($clientName !== null) {
            $file = new UploadedFile($file->getPathname(), $clientName, $mime, null, true);
        }

        return $file;
    }
}
