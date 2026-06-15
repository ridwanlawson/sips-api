<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

trait ImageOptimizerTrait
{
    protected function optimizeAndSaveImage(
        UploadedFile $file,
        string $folderPath,
    ): string {
        $destinationPath = public_path($folderPath);

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0777, true);
        }

        $filename = time() . "_" . $file->getClientOriginalName();
        $manager = new ImageManager(new Driver());

        $manager
            ->read($file)
            ->save($destinationPath . "/" . $filename, quality: 75, strip: true);

        return $folderPath . "/" . $filename;
    }
}
