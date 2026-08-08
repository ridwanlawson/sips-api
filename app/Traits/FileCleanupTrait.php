<?php

namespace App\Traits;

use App\Services\StorageService;

trait FileCleanupTrait
{
    protected array $uploadedFiles = [];

    protected function trackUploadedFile(?string $url): void
    {
        if ($url) {
            $this->uploadedFiles[] = $url;
        }
    }

    protected function cleanupUploadedFiles(): void
    {
        $storage = app(StorageService::class);

        foreach (array_unique(array_filter($this->uploadedFiles)) as $url) {
            $storage->deleteFile($url);
        }

        $this->uploadedFiles = [];
    }
}
