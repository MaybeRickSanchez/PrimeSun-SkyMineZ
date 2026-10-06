<?php

declare(strict_types=1);

namespace AM\SkyMineZ\config;

use AM\SkyMineZ\Main;
use pocketmine\utils\Config;

/**
 * Thin typed wrapper around the plugin's config.yml.
 *
 * Everything is addressed with a dot path ("scoreboard.spawn-radius") so the
 * YAML layout stays the single source of truth and no PHP default can silently
 * disagree with the shipped file.
 */
final class ConfigManager
{
    private const FILE_NAME = 'config.yml';

    private Config $config;

    public function __construct(
        private Main $main
    ) {
        $this->config = $this->load();
    }

    public function reload(): void
    {
        $this->config = $this->load();
    }

    public function save(): void
    {
        $this->config->save();
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return $this->config->getNested($path, $default);
    }

    public function set(
        string $path,
        mixed $value
    ): void {
        $this->config->setNested($path, $value);
    }

    public function getString(
        string $path,
        string $default = ''
    ): string {
        $value = $this->config->getNested($path, $default);

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    public function getInt(
        string $path,
        int $default = 0
    ): int {
        $value = $this->config->getNested($path, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function getFloat(
        string $path,
        float $default = 0.0
    ): float {
        $value = $this->config->getNested($path, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function getBool(
        string $path,
        bool $default = false
    ): bool {
        $value = $this->config->getNested($path, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', 'yes', 'on', '1' => true,
                'false', 'no', 'off', '0' => false,
                default => $default
            };
        }

        return is_numeric($value) ? ((int) $value) !== 0 : $default;
    }

    /**
     * Reads a list of strings, silently skipping anything that is not scalar.
     *
     * @return list<string>
     */
    public function getStringList(string $path): array
    {
        $value = $this->config->getNested($path, []);

        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $entry) {
            if (is_scalar($entry)) {
                $result[] = (string) $entry;
            }
        }

        return $result;
    }

    public function getPrefix(): string
    {
        return $this->getString('prefix');
    }

    private function load(): Config
    {
        $dataFolder = $this->main->getDataFolder();

        if (!is_dir($dataFolder)) {
            @mkdir(
                $dataFolder,
                0777,
                true
            );
        }

        $this->main->saveResource(self::FILE_NAME);

        return new Config(
            $dataFolder . self::FILE_NAME,
            Config::YAML
        );
    }
}