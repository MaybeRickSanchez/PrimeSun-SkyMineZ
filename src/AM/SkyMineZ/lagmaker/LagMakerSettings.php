<?php

declare(strict_types=1);

namespace AM\SkyMineZ\lagmaker;

use AM\SkyMineZ\config\ConfigManager;

/**
 * One tick's worth of lagmaker tuning, snapshotted so every decision inside a
 * tick (and inside one cleanup pass) sees the same values even if an admin
 * runs a config command halfway through.
 */
final class LagMakerSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly bool $autoStack,
        public readonly float $stackRadius,
        public readonly int $maxDropsPerPlayer,
        public readonly string $mode,
        public readonly int $ttlTicks,
        public readonly int $cleanupInterval,
        public readonly int $cleanupPerTick
    ) {
    }

    public static function fromConfig(
        ConfigManager $config
    ): self {
        $mode = strtolower(
            $config->getString(
                'lagmaker.cleanup.mode',
                LagMaker::MODE_TTL
            )
        );

        if (
            $mode !== LagMaker::MODE_OFF
            && $mode !== LagMaker::MODE_ALL
        ) {
            $mode = LagMaker::MODE_TTL;
        }

        return new self(
            $config->getBool('lagmaker.enabled', true),
            $config->getBool('lagmaker.auto-stack', true),
            max(
                0.5,
                $config->getFloat('lagmaker.stack-radius', 1.5)
            ),
            max(
                0,
                $config->getInt('lagmaker.max-drops-per-player', 15)
            ),
            $mode,
            max(
                0,
                $config->getInt('lagmaker.cleanup.ttl-seconds', 900)
            ) * 20,
            max(
                20,
                $config->getInt('lagmaker.cleanup.interval', 36000)
            ),
            max(
                1,
                $config->getInt('lagmaker.cleanup.per-tick', 200)
            )
        );
    }

    public function cleanupDisabled(): bool
    {
        return $this->mode === LagMaker::MODE_OFF;
    }

    public function removeEverything(): bool
    {
        return $this->mode === LagMaker::MODE_ALL;
    }
}