<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

/**
 * Small array-shape guards shared by every manager that reads JSON stores.
 *
 * JSON objects decode to string-keyed arrays, but a hand-edited file can
 * contain anything. Centralizing these checks keeps the loaders free of
 * copy-pasted validation prologues that would otherwise drift apart.
 */
final class Arrays
{
    private function __construct()
    {
    }

    /**
     * @param array<mixed> $array
     *
     * @phpstan-assert-if-true array<string, mixed> $array
     */
    public static function isStringMap(
        array $array
    ): bool {
        foreach (
            array_keys($array) as $key
        ) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A dense [x, y, z] triple of numbers, the shape every position-like JSON
     * record uses.
     *
     * @param mixed $value
     *
     * @phpstan-assert-if-true array{float, float, float} $value
     */
    public static function isVectorTriple(
        mixed $value
    ): bool {
        return is_array($value)
            && isset($value[0], $value[1], $value[2])
            && is_numeric($value[0])
            && is_numeric($value[1])
            && is_numeric($value[2]);
    }
}