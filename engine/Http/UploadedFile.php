<?php
declare(strict_types=1);

namespace Oshim\Http;

use RuntimeException;

class UploadedFile
{
    public function __construct(
        private string $clientFilename,
        private string $clientMediaType,
        private string $tempFilePath,
        private int $size,
        private int $error = UPLOAD_ERR_OK,
        private bool $isTest = false
    ) {}

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && ($this->isTest || is_uploaded_file($this->tempFilePath));
    }

    public function getClientFilename(): string
    {
        return $this->clientFilename;
    }

    public function getClientExtension(): string
    {
        return pathinfo($this->clientFilename, PATHINFO_EXTENSION);
    }

    public function getClientMediaType(): string
    {
        return $this->clientMediaType;
    }

    public function getTempFilePath(): string
    {
        return $this->tempFilePath;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK         => 'There is no error, the file uploaded with success.',
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            default               => 'Unknown upload error.',
        };
    }

    public function getContents(): string
    {
        if (!is_file($this->tempFilePath)) {
            return '';
        }
        return (string)file_get_contents($this->tempFilePath);
    }

    /**
     * Move the uploaded file to a destination directory.
     */
    public function moveTo(string $destinationDir, ?string $filename = null): string
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Cannot move uploaded file with error: " . $this->getErrorMessage());
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $targetName = $filename ?? basename($this->clientFilename);
        // Sanitize target name to prevent path traversal
        $targetName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $targetName) ?: 'uploaded_file';
        $targetPath = rtrim($destinationDir, '/\\') . DIRECTORY_SEPARATOR . $targetName;

        if ($this->isTest) {
            if (!copy($this->tempFilePath, $targetPath)) {
                throw new RuntimeException("Failed to copy test file to destination: [{$targetPath}]");
            }
        } else {
            if (!move_uploaded_file($this->tempFilePath, $targetPath)) {
                throw new RuntimeException("Failed to move uploaded file to destination: [{$targetPath}]");
            }
        }

        return $targetPath;
    }

    /**
     * Factory from $_FILES item array.
     */
    public static function createFromGlobal(array $fileArray): self
    {
        return new self(
            clientFilename: (string)($fileArray['name'] ?? ''),
            clientMediaType: (string)($fileArray['type'] ?? 'application/octet-stream'),
            tempFilePath: (string)($fileArray['tmp_name'] ?? ''),
            size: (int)($fileArray['size'] ?? 0),
            error: (int)($fileArray['error'] ?? UPLOAD_ERR_NO_FILE),
            isTest: false
        );
    }
}
