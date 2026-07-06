<?php

namespace Core;

class ArrayHandler
{
    public static function hasParamTrue(string $param, array $array): bool
    {
        return self::hasParam($param, $array) && $array[$param] === true;
    }

    public static function hasParam(string $param, array $array): bool
    {
        return isset($array[$param]);
    }

    public static function getValueAsInt(string $param, array $array): int
    {
        return self::hasParam($param, $array) ? (int)$array[$param] : 0;
    }

    public static function getValueAsArray(string $param, array $array): array
    {
        return self::hasParam($param, $array) && is_array($array[$param]) ? $array[$param] : [];
    }

    public static function getValueAsString(string $param, array $array): string
    {
        return self::hasParam($param, $array)
            ? htmlspecialchars($array[$param], ENT_QUOTES)
            : '';
    }

    // @todo проверить как сам PDO через params подставляет
    public static function getUnsafeValueAsString(string $param, array $array): string
    {
        return self::hasParam($param, $array)
            ? trim($array[$param])
            : '';
    }

    public static function getValueAsBool(string $param, array $array): bool
    {
        return self::hasParam($param, $array) ? (bool)$array[$param] : false;
    }

    public static function hasParamThroughException(string $param, array $array, string $message = null)
    {
        if (!self::hasParam($param, $array)) {
            $message = $message ?? "Required param '{$param}' not found";
            throw new \Exception($message);
        }
    }
}
