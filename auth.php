<?php
// 認証チェック（各ページの先頭でrequire）
require_once __DIR__ . '/config.php';

function requireLogin(): void {
    if (!isset($_SESSION['employee_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: dashboard.php');
        exit;
    }
}

function currentEmployeeId(): int {
    return (int)$_SESSION['employee_id'];
}

function currentEmployeeName(): string {
    return $_SESSION['employee_name'] ?? '';
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

// 勤務時間計算（分単位）
function calcWorkMinutes(?string $clockIn, ?string $clockOut, ?string $breakStart, ?string $breakEnd): int {
    if (!$clockIn || !$clockOut) return 0;
    $work  = (strtotime($clockOut) - strtotime($clockIn)) / 60;
    $break = 0;
    if ($breakStart && $breakEnd) {
        $break = (strtotime($breakEnd) - strtotime($breakStart)) / 60;
    }
    return max(0, (int)($work - $break));
}

// 分を「H時間M分」表示
function formatMinutes(int $minutes): string {
    if ($minutes <= 0) return '0時間';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}時間" . ($m > 0 ? "{$m}分" : '') : "{$m}分";
}