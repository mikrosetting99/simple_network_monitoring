<?php
declare(strict_types=1);

/** Import perangkat dari baris tabel (hasil baca .xlsx atau .csv): kolom nama, ip, grup. */
final class DeviceImporter
{
    private const MAX_ROWS = 5000;

    /** @return array<int,array<int,string>> */
    public static function rowsFromUpload(string $tmpPath, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            if (!Xlsx::available()) {
                throw new RuntimeException('Ekstensi zip/SimpleXML PHP belum aktif; simpan file sebagai CSV lalu import ulang.');
            }
            return Xlsx::read($tmpPath, self::MAX_ROWS);
        }
        if ($ext === 'csv' || $ext === 'txt') {
            return self::readCsv($tmpPath);
        }
        throw new RuntimeException('Format file harus .xlsx atau .csv (file .xls lama: simpan ulang sebagai .xlsx).');
    }

    private static function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $first = (string) fgets($fh);
        rewind($fh);
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ','; // Excel Indonesia memakai ;
        $rows = [];
        while (($cols = fgetcsv($fh, 0, $delim)) !== false && count($rows) <= self::MAX_ROWS) {
            $rows[] = array_map('trim', $cols);
        }
        fclose($fh);
        return $rows;
    }

    /** @return array{added:int, duplicate:int, invalid:int, invalidLines:int[], groupsCreated:int} */
    public static function import(array $rows): array
    {
        $res = ['added' => 0, 'duplicate' => 0, 'invalid' => 0, 'invalidLines' => [], 'groupsCreated' => 0];

        // Buang BOM, lalu buang baris header (kolom pertama "nama" dan IP tidak valid).
        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }
        $startLine = 1;
        // "ip" sendiri lolos sebagai hostname satu-label, jadi header dikenali dari kata "nama" + kolom kedua bukan IP/FQDN.
        $second = $rows[0][1] ?? '';
        if ($rows && preg_match('/^(nama|name)\b/i', $rows[0][0] ?? '') && !filter_var($second, FILTER_VALIDATE_IP) && !str_contains($second, '.')) {
            array_shift($rows);
            $startLine = 2;
        }

        $groupIds = array_column(Database::all('SELECT id, name FROM `groups`'), 'id', 'name');
        $existing = array_flip(array_map('strtolower', array_column(Database::all('SELECT ip_address FROM devices'), 'ip_address')));
        $interval = max(10, (int) Settings::get('default_interval'));
        $threshold = max(1, (int) Settings::get('default_threshold'));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $i => $cols) {
                $line = $i + $startLine;
                $name = trim((string) ($cols[0] ?? ''));
                $ip = trim((string) ($cols[1] ?? ''));
                $grp = trim((string) ($cols[2] ?? ''));
                if ($name === '' && $ip === '' && $grp === '') {
                    continue; // baris kosong
                }
                if ($name === '' || !PingService::isValidTarget($ip)) {
                    $res['invalid']++;
                    $res['invalidLines'][] = $line;
                    continue;
                }
                if (isset($existing[strtolower($ip)])) {
                    $res['duplicate']++;
                    continue;
                }
                $gid = null;
                if ($grp !== '') {
                    if (!isset($groupIds[$grp])) {
                        Database::query('INSERT INTO `groups` (name) VALUES (?)', [mb_substr($grp, 0, 100)]);
                        $groupIds[$grp] = (int) $pdo->lastInsertId();
                        $res['groupsCreated']++;
                    }
                    $gid = $groupIds[$grp];
                }
                Database::query(
                    'INSERT INTO devices (group_id, name, ip_address, interval_sec, fail_threshold) VALUES (?, ?, ?, ?, ?)',
                    [$gid, mb_substr($name, 0, 100), $ip, $interval, $threshold]
                );
                $existing[strtolower($ip)] = true;
                $res['added']++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $res;
    }
}
