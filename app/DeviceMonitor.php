<?php
declare(strict_types=1);

/** Menulis hasil ping dan membuka/menutup insiden saat status berubah. */
final class DeviceMonitor
{
    /**
     * @param array $device baris dari tabel devices
     * @param array $res    hasil PingService::parse
     */
    public static function record(array $device, array $res, string $now): void
    {
        $id = (int) $device['id'];
        $status = $res['up'] ? 'up' : 'down';

        Database::query(
            'INSERT INTO ping_logs (device_id, checked_at, status, latency_ms, packet_loss) VALUES (?, ?, ?, ?, ?)',
            [$id, $now, $status, $res['latency'], $res['loss']]
        );

        $inMaintenance = $device['maintenance_until'] !== null && $device['maintenance_until'] > $now;
        $current = $device['current_status'];
        $failCount = (int) $device['fail_count'];
        $downSince = $device['down_since'];

        if ($res['up']) {
            if ($current === 'down') {
                self::closeIncident($id, $now);
            }
            $current = 'up';
            $failCount = 0;
            $downSince = null;
        } else {
            $failCount++;
            if (!$inMaintenance && $current !== 'down' && $failCount >= (int) $device['fail_threshold']) {
                // Waktu mulai down = ping gagal pertama dari rentetan terakhir.
                $downSince = (string) Database::value(
                    'SELECT MIN(checked_at) FROM (SELECT checked_at FROM ping_logs WHERE device_id = ? ORDER BY checked_at DESC LIMIT ' . (int) $device['fail_threshold'] . ') t',
                    [$id]
                ) ?: $now;
                $current = 'down';
                Database::query('INSERT INTO incidents (device_id, started_at) VALUES (?, ?)', [$id, $downSince]);
            }
        }

        Database::query(
            'UPDATE devices SET current_status = ?, fail_count = ?, last_checked_at = ?, last_latency_ms = ?, down_since = ? WHERE id = ?',
            [$current, $failCount, $now, $res['latency'], $downSince, $id]
        );
    }

    public static function closeIncident(int $deviceId, string $now): void
    {
        Database::query(
            'UPDATE incidents SET ended_at = ?, duration_sec = TIMESTAMPDIFF(SECOND, started_at, ?) WHERE device_id = ? AND ended_at IS NULL',
            [$now, $now, $deviceId]
        );
    }

    /** Perangkat dinonaktifkan: tutup insiden yang masih terbuka dan reset status. */
    public static function deactivate(int $deviceId): void
    {
        self::closeIncident($deviceId, date('Y-m-d H:i:s'));
        Database::query(
            "UPDATE devices SET current_status = 'unknown', fail_count = 0, down_since = NULL, last_latency_ms = NULL WHERE id = ?",
            [$deviceId]
        );
    }
}
