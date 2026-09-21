<?php
declare(strict_types=1);

final class PingService
{
    public static function isValidTarget(string $target): bool
    {
        if ($target === '' || $target[0] === '-' || strlen($target) > 253) {
            return false;
        }
        if (filter_var($target, FILTER_VALIDATE_IP)) {
            return true;
        }
        return (bool) filter_var($target, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /** Kemampuan yang dibutuhkan worker: proc_open dan tidak masuk disable_functions. */
    public static function canRun(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /** Bentuk array dipakai supaya tidak lewat shell; target sudah divalidasi. */
    private static function command(string $target): array
    {
        return self::isWindows()
            ? ['ping', '-n', '3', '-w', '1000', $target]
            : ['ping', '-c', '3', '-W', '1', $target];
    }

    /** Baris balasan sukses selalu memuat TTL, baik di Windows maupun Linux (juga di Windows berbahasa Indonesia). */
    public static function parse(string $output): array
    {
        $received = 0;
        $latencies = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (!preg_match('/\bttl=/i', $line)) {
                continue;
            }
            $received++;
            if (preg_match('/(?:time|waktu)\s*([=<])\s*([\d.,]+)\s*ms/i', $line, $m)) {
                $latencies[] = $m[1] === '<' ? 0.5 : (float) str_replace(',', '.', $m[2]);
            }
        }
        $sent = 3;
        return [
            'up'      => $received > 0,
            'latency' => $latencies ? round(array_sum($latencies) / count($latencies), 2) : null,
            'loss'    => (int) round(($sent - min($received, $sent)) / $sent * 100),
        ];
    }

    /**
     * Ping banyak target secara paralel (batch $parallel proses).
     * Output ke file sementara karena pipe Windows tidak bisa non-blocking.
     *
     * @param array<int,string> $targets id => host
     * @return array<int,array{up:bool,latency:?float,loss:int}>
     */
    public static function runBatch(array $targets, int $parallel = 30, int $timeout = 5): array
    {
        $results = [];
        $queue = [];
        foreach ($targets as $id => $host) {
            if (self::isValidTarget($host)) {
                $queue[$id] = $host;
            } else {
                $results[$id] = ['up' => false, 'latency' => null, 'loss' => 100];
            }
        }

        $null = self::isWindows() ? 'NUL' : '/dev/null';
        $running = [];

        while ($queue || $running) {
            while ($queue && count($running) < $parallel) {
                $id = array_key_first($queue);
                $host = $queue[$id];
                unset($queue[$id]);

                $tmp = tempnam(sys_get_temp_dir(), 'png');
                $proc = @proc_open(
                    self::command($host),
                    [0 => ['pipe', 'r'], 1 => ['file', $tmp, 'w'], 2 => ['file', $null, 'w']],
                    $pipes
                );
                if (!is_resource($proc)) {
                    @unlink($tmp);
                    $results[$id] = ['up' => false, 'latency' => null, 'loss' => 100];
                    continue;
                }
                fclose($pipes[0]);
                $running[$id] = ['proc' => $proc, 'tmp' => $tmp, 'start' => microtime(true)];
            }

            foreach ($running as $id => $job) {
                $alive = proc_get_status($job['proc'])['running'];
                $timedOut = microtime(true) - $job['start'] > $timeout;
                if ($alive && !$timedOut) {
                    continue;
                }
                if ($alive) {
                    proc_terminate($job['proc']);
                }
                proc_close($job['proc']);
                $results[$id] = self::parse((string) @file_get_contents($job['tmp']));
                @unlink($job['tmp']);
                unset($running[$id]);
            }

            if ($running) {
                usleep(20000);
            }
        }
        return $results;
    }

    public static function checkOne(string $host, int $timeout = 5): array
    {
        return self::runBatch([0 => $host], 1, $timeout)[0];
    }
}
