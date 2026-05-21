<?php
// データベース設定
define('DB_HOST', 'localhost');
define('DB_NAME', 'attendance_db');
define('DB_USER', 'root');        // 適宜変更してください
define('DB_PASS', '');            // 適宜変更してください
define('DB_CHARSET', 'utf8mb4');

// PDO接続
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('データベース接続エラー: ' . $e->getMessage());
        }
    }
    return $pdo;
}

// セッション設定
session_start();
define('APP_NAME', '勤怠管理システム');
define('TIMEZONE', 'Asia/Tokyo');
date_default_timezone_set(TIMEZONE);