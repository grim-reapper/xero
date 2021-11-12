<?php

namespace Imran\Xero;

use Imran\Xero\Contracts\StorageContract;

class FileStorage implements StorageContract
{

    const FILE_NAME = './storage.txt';

    /**
     * @param array $data
     */
    public static function write(array $data): void
    {
        file_put_contents(self::FILE_NAME, json_encode($data));
    }

    /**
     * @param string $key
     * @return mixed|string
     */
    public static function read(string $key): string
    {
        $parseContent = self::readFile();

        if (array_key_exists($key, $parseContent)) {
            return $parseContent[$key];
        }
        return '';
    }

    public static function all(): array
    {
        return self::readFile();
    }

    private static function readFile(): array
    {
        if (self::fileExists()) {
            $content = file_get_contents(self::FILE_NAME);
            return json_decode($content, true);
        }
        return [];
    }

    private static function fileExists(): bool
    {
        return file_exists(self::FILE_NAME);
    }

    public static function getAccessToken()
    {
        return self::read('token') <= time() ? self::read('token') : null;
    }

    public static function getRefreshToken()
    {
        return self::read('refresh_token');
    }

    public static function getIdToken()
    {
        return self::read('id_token');
    }

    public static function getXeroTenantId()
    {
        return self::read('tenant_id');
    }

    public static function getExpires()
    {
        return self::read('expires');
    }

    public static function hasExpired(): bool
    {
        return time() > self::getExpires();
    }

    public static function getByKey(string $key, array $array)
    {
        if($key && array_key_exists($key, $array)){
            return $array[$key];
        }
        return '';
    }

    public static function keyExist(string $key, array $array): bool
    {
        return array_key_exists($key, $array);
    }
}
