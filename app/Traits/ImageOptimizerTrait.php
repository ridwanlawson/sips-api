<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait ImageOptimizerTrait
{
    protected function optimizeAndSaveImage(
        UploadedFile $file,
        string $folderPath,
    ): string {
        $destinationPath = public_path($folderPath);

        if (! file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $isAnimatedGif = $extension === 'gif';
        $isAlreadyWebp = $extension === 'webp';

        // File yang sudah WebP atau GIF (berpotensi animasi) disimpan apa adanya
        // untuk mencegah degradasi dan kerusakan animasi.
        if ($isAlreadyWebp || $isAnimatedGif) {
            $filename = time().'_'.$file->getClientOriginalName();
            $file->move($destinationPath, $filename);

            return $folderPath.'/'.$filename;
        }

        $filename = time().'_'.$baseName.'.webp';
        $manager = new ImageManager(new Driver);

        $image = $manager->read($file);

        // Deteksi transparansi: jika ada alpha channel, gunakan WebP lossless
        // agar kualitas visual dan transparansi tetap utuh.
        $hasAlpha = false;
        if (method_exists($image, 'blendingColor')) {
            $color = $image->blendingColor();
            $hasAlpha = $color && $color->isTransparent();
        }

        if ($hasAlpha) {
            $image->toWebp(quality: 100, strip: true)->save($destinationPath.'/'.$filename);
        } else {
            $image->toWebp(quality: 80, strip: true)->save($destinationPath.'/'.$filename);
        }

        return $folderPath.'/'.$filename;
    }
}
