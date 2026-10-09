<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

class ProductImageService
{
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function inspect(string $path, int $size): string
    {
        if ($size < 1 || $size > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Choose an image up to 2 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $dimensions = @getimagesize($path);
        if (! isset(self::TYPES[$mime]) || ! $dimensions || ($dimensions['mime'] ?? '') !== $mime) {
            throw new \InvalidArgumentException('Only JPEG, PNG and WebP images are accepted.');
        }
        if ($dimensions[0] > 4096 || $dimensions[1] > 4096 || $dimensions[0] < 1 || $dimensions[1] < 1) {
            throw new \InvalidArgumentException('Image dimensions must be between 1 and 4096 pixels.');
        }
        return self::TYPES[$mime];
    }

    public function store(UploadedFile $file): string
    {
        if (! $file->isValid() || $file->hasMoved()) {
            throw new \InvalidArgumentException('Choose a valid image upload up to 2 MB.');
        }
        $extension = $this->inspect($file->getTempName(), $file->getSize());
        $name = bin2hex(random_bytes(24)) . '.' . $extension;
        $file->move(WRITEPATH . 'uploads/products', $name);
        return 'media/products/' . $name;
    }

    public function remove(?string $image): void
    {
        if ($image && preg_match('#^media/products/([a-f0-9]{48}\.(?:jpg|png|webp))$#D', $image, $matches)) {
            $path = WRITEPATH . 'uploads/products/' . $matches[1];
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
