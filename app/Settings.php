<?php
declare(strict_types=1);

final class Settings
{
    public const DEFAULTS = [
        'default_interval'  => '60',
        'default_threshold' => '3',
        'retention_days'    => '14',
        'sound_alert'       => '0',
        'last_cycle_at'     => '',
        'last_cycle_sec'    => '',
        'last_cycle_count'  => '',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            foreach (Database::all('SELECT k, v FROM settings') as $r) {
                self::$cache[$r['k']] = $r['v'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key): string
    {
        return self::all()[$key] ?? '';
    }

    public static function set(string $key, string $value): void
    {
        Database::query('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
        self::$cache = null;
    }
}
