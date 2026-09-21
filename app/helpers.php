<?php
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $msg = null, string $type = 'success'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Tanpa login, CSRF tetap perlu agar halaman lain di LAN tidak bisa memicu aksi ubah/hapus. */
function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), (string) $sent)) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function fmt_duration(int $sec): string
{
    if ($sec < 60) {
        return $sec . 'd';
    }
    $parts = [];
    $days = intdiv($sec, 86400);
    $hours = intdiv($sec % 86400, 3600);
    $mins = intdiv($sec % 3600, 60);
    if ($days) {
        $parts[] = $days . 'h';
    }
    if ($hours) {
        $parts[] = $hours . 'j';
    }
    if ($mins && count($parts) < 2) {
        $parts[] = $mins . 'm';
    }
    return implode(' ', $parts);
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Peringatan bila worker belum berjalan lebih dari 5 menit; null jika sehat. */
function worker_health(): array
{
    $last = Settings::get('last_cycle_at');
    if ($last === '') {
        return ['ok' => false, 'text' => 'Worker belum pernah berjalan'];
    }
    $age = time() - strtotime($last);
    return ['ok' => $age <= 300, 'text' => 'Siklus terakhir ' . fmt_duration($age) . ' lalu', 'age' => $age];
}
