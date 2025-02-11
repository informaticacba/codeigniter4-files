<?php

namespace Tatter\Files\Entities;

use CodeIgniter\Entity\Entity;
use CodeIgniter\Files\Exceptions\FileNotFoundException;
use Config\Mimes;
use Tatter\Files\Structures\FileObject;

class File extends Entity
{
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Resolved path to the default thumbnail
     */
    protected static ?string $defaultThumbnail;

    /**
     * Returns the absolute path to the configured default thumbnail
     *
     * @throws FileNotFoundException
     */
    public static function locateDefaultThumbnail(): string
    {
        // If the path has not been resolved yet then try to now
        if (null === self::$defaultThumbnail) {
            $path = config('Files')->defaultThumbnail;
            $ext  = pathinfo($path, PATHINFO_EXTENSION);

            if (! self::$defaultThumbnail = service('locator')->locateFile($path, null, $ext)) {
                throw new FileNotFoundException('Could not locate default thumbnail: ' . $path);
            }
        }

        return (string) self::$defaultThumbnail;
    }

    //--------------------------------------------------------------------

    /**
     * Returns the full path to this file
     */
    public function getPath(): string
    {
        $path = config('Files')->getPath() . $this->attributes['localname'];

        return realpath($path) ?: $path;
    }

    /**
     * Returns the most likely actual file extension
     *
     * @param string $method Explicit method to use for determining the extension
     */
    public function getExtension($method = ''): string
    {
        if ($this->attributes['type'] !== 'application/octet-stream') {
            if ((! $method || $method === 'type') && ($extension = Mimes::guessExtensionFromType($this->attributes['type']))) {
                return $extension;
            }

            if ((! $method || $method === 'mime') && ($file = $this->getObject()) && ($extension = $file->guessExtension())) {
                return $extension;
            }
        }

        foreach (['clientname', 'localname', 'filename'] as $attribute) {
            if ((! $method || $method === $attribute) && ($extension = pathinfo($this->attributes[$attribute], PATHINFO_EXTENSION))) {
                return $extension;
            }
        }

        return '';
    }

    /**
     * Returns a FileObject (CIFile/SplFileInfo) for the local file
     *
     * @return FileObject|null `null` for missing file
     */
    public function getObject(): ?FileObject
    {
        try {
            return new FileObject($this->getPath(), true);
        } catch (FileNotFoundException $e) {
            return null;
        }
    }

    /**
     * Returns class names of Exports applicable to this file's extension
     *
     * @param bool $asterisk Whether to include generic "*" extensions
     *
     * @return string[]
     */
    public function getExports($asterisk = true): array
    {
        $exports = [];

        if ($extension = $this->getExtension()) {
            $exports = handlers('Exports')->where(['extensions has' => $extension])->findAll();
        }

        if ($asterisk) {
            $exports = array_merge(
                $exports,
                handlers('Exports')->where(['extensions' => '*'])->findAll()
            );
        }

        return $exports;
    }

    /**
     * Returns the path to this file's thumbnail, or the default from config.
     * Should always return a path to a valid file to be safe for img_data()
     */
    public function getThumbnail(): string
    {
        $path = config('Files')->getPath() . 'thumbnails' . DIRECTORY_SEPARATOR . ($this->attributes['thumbnail'] ?? '');

        if (! is_file($path)) {
            $path = self::locateDefaultThumbnail();
        }

        return realpath($path) ?: $path;
    }
}
