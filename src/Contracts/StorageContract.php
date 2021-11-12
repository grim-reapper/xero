<?php

namespace GrimReapper\Xero\Contracts;

interface StorageContract
{
    public static function write(array $data);

    public static function read(string $key);

    public static function all();

    public static function hasExpired();
}
