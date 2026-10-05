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

    public function has(string $path): bool
    {
        return $this->config->exists($path, true);
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

    /**
     * Builds a message from the config, applying {placeholders}.
     *
     * Unknown placeholders are left untouched so a typo is visible in-game
     * instead of silently swallowing text.
     *
     * @param array<string, string|int|float|bool> $placeholders
     */
    public function message(
        string $path,
        array $placeholders = []
    ): string {
        $template = $this->getString($path);

        if ($template === '') {
            return '';
        }

        $search = [];
        $replace = [];

        foreach ($placeholders as $key => $value) {
            $search[] = '{' . $key . '}';

            $replace[] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_float($value) => rtrim(
                    rtrim(
                        number_format($value, 2, '.', ''),
                        '0'
                    ),
                    '.'
                ),
                default => (string) $value
            };
        }

        return str_replace(
            $search,
            $replace,
            $template
        );
    }

    public function getPrefix(): string
    {
        return $this->getString('prefix');
    }

    /**
     * Message plus the configured prefix.
     *
     * @param array<string, string|int|float|bool> $placeholders
     */
    public function prefixed(
        string $path,
        array $placeholders = []
    ): string {
        return $this->getPrefix() . $this->message(
            $path,
            $placeholders
        );
    }

    /**
     * Resolves a case-insensitive dot path, used by commands where the user
     * types the key themselves (for example /lagmaker cleanup mode).
     */
    public function resolveCaseInsensitive(string $path): ?string
    {
        $current = $this->config->getAll();
        $walked = [];

        foreach (explode('.', $path) as $segment) {
            $found = null;

            if (is_array($current)) {
                foreach ($current as $key => $value) {
                    if (strcasecmp((string) $key, $segment) === 0) {
                        $found = $value;

                        $walked[] = (string) $key;

                        break;
                    }
                }
            }

            if ($found === null) {
                return null;
            }

            $current = $found;
        }

        return implode('.', $walked);
    }

    public function getConfig(): Config
    {
        return $this->config;
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