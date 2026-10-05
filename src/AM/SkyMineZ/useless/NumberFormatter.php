<?php

declare(strict_types=1);

namespace AM\SkyMineZ\useless;

/**
 * Shortens large numbers for scoreboards and leaderboards: 999, 1.2K, 3.4M and
 * so on. Once the suffix table runs out the value falls back to scientific
 * notation, so the result never grows without bound.
 */
final class NumberFormatter
{
    private const SUFFIXES = [
        'K',
        'M',
        'B',
        'T',
        'Qa',
        'Qi',
        'Sx',
        'Sp',
        'Oc',
        'No',
        'Dc',
        'Ud',
        'Dd',
        'Td',
        'Qad',
        'Qid',
        'Sxd',
        'Spd',
        'Ocd',
        'Nod'
    ];

    private function __construct()
    {
    }

    public static function short(
        int|float $number
    ): string {
        $negative = $number < 0;

        $value = abs((float) $number);
        $lastIndex = count(self::SUFFIXES) - 1;

        if ($value < 1000) {
            return ($negative ? '-' : '') . number_format(
                $value,
                0,
                '.',
                ''
            );
        }

        $index = -1;

        while (
            $value >= 1000
            && $index < $lastIndex
        ) {
            $value /= 1000;
            ++$index;
        }

        // 999999 would otherwise print as "1000K": bump it up one tier.
        if (
            $value >= 999.995
            && $index < $lastIndex
        ) {
            $value /= 1000;
            ++$index;
        }

        if (
            $index === $lastIndex
            && $value >= 1000
        ) {
            return ($negative ? '-' : '') . sprintf(
                '%.2e',
                $value
            );
        }

        return ($negative ? '-' : '')
            . rtrim(
                rtrim(
                    number_format(
                        $value,
                        2,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            )
            . self::SUFFIXES[$index];
    }

    /**
     * 1234.50 -> "1234.5", 1200.00 -> "1200"
     */
    public static function trim(float $number): string
    {
        $formatted = rtrim(
            number_format(
                $number,
                2,
                '.',
                ''
            ),
            '0'
        );

        return rtrim(
            $formatted,
            '.'
        );
    }

    /**
     * Renders a progress bar of the given length, e.g. "[###-----]".
     */
    public static function bar(
        float $ratio,
        int $length = 10,
        string $filled = '#',
        string $empty = '-'
    ): string {
        $ratio = max(
            0.0,
            min(1.0, $ratio)
        );

        $count = (int) round(
            $ratio * $length
        );

        return '[' . str_repeat(
            $filled,
            $count
        ) . str_repeat(
            $empty,
            $length - $count
        ) . ']';
    }

    /**
     * 95 -> "1m35s", 3700 -> "1h1m", used by the mine and outpost holograms.
     */
    public static function duration(int $seconds): string
    {
        if ($seconds < 0) {
            $seconds = 0;
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv(
            $seconds % 86400,
            3600
        );
        $minutes = intdiv(
            $seconds % 3600,
            60
        );

        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        }

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }

        if ($minutes > 0) {
            return $minutes . 'm ' . ($seconds % 60) . 's';
        }

        return $seconds . 's';
    }
}