<?php

declare(strict_types=1);

namespace AlumniConnect\Services;

use AlumniConnect\Repositories\MemoryRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MemoryUploadService
{
    private const MAX_BYTES = 2097152;
    private const MAX_DIMENSION = 8000;
    private const MAX_PIXELS = 20000000;

    private MemoryRepository $memories;

    public function __construct()
    {
        $this->memories = new MemoryRepository();
    }

    public function upload(int $eventId, int $uploaderId, mixed $uploadedFile, string $caption): int
    {
        if (!is_array($uploadedFile) || !isset($uploadedFile['error'], $uploadedFile['tmp_name'])) {
            throw new InvalidArgumentException('Choose a JPEG or PNG image to upload.');
        }

        $uploadError = filter_var($uploadedFile['error'], FILTER_VALIDATE_INT);
        if (!is_int($uploadError)) {
            throw new InvalidArgumentException('The image upload could not be read.');
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new InvalidArgumentException('Images must be no larger than 2 MB.');
            }
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                throw new InvalidArgumentException('Choose a JPEG or PNG image to upload.');
            }
            throw new RuntimeException('The image upload did not complete. Please try again.');
        }

        $temporaryPath = $uploadedFile['tmp_name'];
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('The image upload is not valid.');
        }
        $byteSize = filesize($temporaryPath);
        if (!is_int($byteSize) || $byteSize < 1 || $byteSize > self::MAX_BYTES) {
            throw new InvalidArgumentException('Images must be between 1 byte and 2 MB.');
        }

        $image = getimagesize($temporaryPath);
        if (!is_array($image) || !isset($image[0], $image[1], $image[2])) {
            throw new InvalidArgumentException('The uploaded file is not a valid JPEG or PNG image.');
        }
        $imageType = match ($image[2]) {
            IMAGETYPE_JPEG => ['image/jpeg', '.jpg'],
            IMAGETYPE_PNG => ['image/png', '.png'],
            default => throw new InvalidArgumentException('Only JPEG and PNG images are accepted.'),
        };
        $width = (int) $image[0];
        $height = (int) $image[1];
        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION
            || $width * $height > self::MAX_PIXELS
        ) {
            throw new InvalidArgumentException('The image dimensions are too large to upload.');
        }

        $storageKey = bin2hex(random_bytes(32)) . $imageType[1];
        $targetPath = $this->path($storageKey);
        $this->ensureStorageDirectory();
        if (!move_uploaded_file($temporaryPath, $targetPath)) {
            throw new RuntimeException('The uploaded image could not be stored safely.');
        }
        if (!chmod($targetPath, 0600)) {
            if (!unlink($targetPath)) {
                error_log('[application] Failed to remove a memory upload with unsafe permissions.');
            }
            throw new RuntimeException('The stored image could not be secured.');
        }

        try {
            return $this->memories->addPhoto($eventId, $uploaderId, [
                'storage_key' => $storageKey,
                'mime_type' => $imageType[0],
                'byte_size' => $byteSize,
                'width' => $width,
                'height' => $height,
                'caption' => $caption,
            ]);
        } catch (Throwable $exception) {
            if (!unlink($targetPath)) {
                error_log('[application] Failed to remove an unreferenced memory upload.');
            }
            throw $exception;
        }
    }

    public function delete(int $photoId, int $actorId, bool $isAdmin): void
    {
        $storageKey = $this->memories->deletePhoto($photoId, $actorId, $isAdmin);
        $path = $this->path($storageKey);
        if (!is_file($path)) {
            throw new RuntimeException('The photo record was removed, but its private file is missing.');
        }
        if (!unlink($path)) {
            throw new RuntimeException('The photo record was removed, but its private file could not be deleted.');
        }
    }

    public function pathForKey(string $storageKey): string
    {
        return $this->path($storageKey);
    }

    private function path(string $storageKey): string
    {
        if (!preg_match('/\A[a-f0-9]{64}\.(?:jpg|png)\z/D', $storageKey)) {
            throw new RuntimeException('The private image reference is invalid.');
        }

        return dirname(__DIR__, 2) . '/storage/memories/' . $storageKey;
    }

    private function ensureStorageDirectory(): void
    {
        $storageRoot = dirname(__DIR__, 2) . '/storage';
        $directory = dirname(__DIR__, 2) . '/storage/memories';
        if (is_link($storageRoot) || is_link($directory)) {
            throw new RuntimeException('The private image directory must not be a symbolic link.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('The private image directory could not be created.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('The private image directory is not writable.');
        }
    }
}
