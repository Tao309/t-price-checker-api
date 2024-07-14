<?php

namespace Core;

use RuntimeException;

class Cache
{
    public const TYPE_ARRAY = 'array';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';

    public function __construct()
    {
        $this->init();
    }

    public static function getCache(string $id, string $sourceType = null): mixed
    {
        $fileData = file_get_contents(self::getFilePathById($id));

        $data = unserialize($fileData);

        if ($sourceType) {
            switch($sourceType) {
                case self::TYPE_ARRAY:
                    if (!is_array($data)) {
                        throw new RuntimeException('Not correct cache type \'array\' after unserialize.');
                    }
                    break;
                case self::TYPE_STRING:
                    if (!is_string($data)) {
                        throw new RuntimeException('Not correct cache type \'string\' after unserialize.');
                    }
                    break;
                case self::TYPE_BOOL:
                    if (!is_bool($data)) {
                        throw new RuntimeException('Not correct cache type \'bool\' after unserialize.');
                    }
                    break;
            }
        }

        return $data;
    }

    public static function saveCache(string $id, $data): mixed
    {
        file_put_contents(self::getFilePathById($id), serialize($data));

        return $data;
    }

    public static function isCacheExists(string $id): bool
    {
        return file_exists(self::getFilePathById($id));
    }

    private static function getFilePathById(string $id): string
    {
        return rootPath . '/cache/' . $id . '.tmp';
    }

    private static function init(): void
    {
        $cacheFolder = rootPath . '/cache';

        if (!is_dir($cacheFolder)) {
            mkdir($cacheFolder, 0755);

            $fp = fopen($cacheFolder . '/index.hml', 'wb');
            fwrite($fp, '');
            fclose($fp);
        }
    }
}