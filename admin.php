<?php
require_once 'auth.php';
requireAdmin();

$db = getDB();

// 社員追加処理
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_employee') {
        $code     = trim($_POST['employee_code'] ?? '');
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'employee';

        if ($code && $name && $email && $password) {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO employees (employee_code, name, email, password, role) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$code, $name, $email, $hash, $role]);
                $message = "社員「{$name}」を追加しました。";
                $msgType = 'success';
            } catch (PDOException $e) {
                $message = 'エラー: 社員コードまたはメールアドレスが重複しています。';
                $msgType = 'warn';
            }
        } else {
            $message = '全項目を入力してください。';
            $msgType = 'warn';
        }
    }
}

// 対象年月
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// 全社員一覧
$employees = $db->query('SELECT * FROM employees ORDER BY employee_code')->fetchAll();

// 全社員の今月勤怠サマリー
$summaries = [];
foreach ($employees as $emp) {
    $stmt = $db->prepare(
        'SELECT * FROM attendance WHERE employee_id = ? AND YEAR(date) = ? AND MONTH(date) = ?'
    );
    $stmt->execute([$emp['id'], $year, $month]);
    $records = $stmt->fetchAll();
    $totalMins = 0;
    $days = 0;
    foreach ($records as $r) {
        $m = calcWorkMinutes($r['clock_in'], $r['clock_out'], $r['break_start'], $r['break_end']);
        if ($m > 0) { $totalMins += $m; $days++; }
    }
    $summaries[$emp['id']] = ['minutes' => $totalMins, 'days' => $days];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者 - <?= APP_NAME ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Share+Tech+Mono&display=swap');
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg: #0d0f14; --surface: #161920; --surface2: #1c2029;
            --border: #252830; --accent: #4f9eff; --accent-dim: rgba(79,158,255,0.12);
            --text: #e8eaf0; --text-dim: #6b7280;
            --danger: #ff5f5f; --success: #4fdd9a; --warn: #ffcc44;
        }
        body { font-family: 'Noto Sans JP', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
        body::before {
            content: ''; position: fixed; inset: 0;
            background-image: linear-gradient(rgba(79,158,255,0.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(79,158,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px; pointer-events: none; z-index: 0;
        }
        nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(13,15,20,0.9); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px; height: 56px;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 15px; }
        .nav-links { display: flex; gap: 4px; }
        .nav-links a { color: var(--text-dim); text-decoration: none; font-size: 13px; padding: 6px 14px; border-radius: 6px; transition: background 0.15s, color 0.15s; }
        .nav-links a:hover, .nav-links a.active { background: var(--accent-dim); color: var(--accent); }
        .nav-right a { color: var(--text-dim); text-decoration: none; padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; transition: border-color 0.15s, color 0.15s; }
        .nav-right a:hover { border-color: var(--accent); color: var(--accent); }
        main { position: relative; z-index: 1; max-width: 1000px; margin: 0 auto; padding: 32px 24px; }
        .msg { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 24px; }
        .msg.success { background: rgba(79,221,154,0.1); border: 1px solid rgba(79,221,154,0.3); color: var(--success); }
        .msg.warn    { background: rgba(255,204,68,0.1);  border: 1px solid rgba(255,204,68,0.3);  color: var(--warn); }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .section-title { font-size: 13px; font-weight: 500; color: var(--text-dim); letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 20px; }
        h2 { font-size: 18px; margin-bottom: 4px; }
        .month-nav { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .month-nav a { color: var(--accent); text-decoration: none; padding: 6px 16px; border: 1px solid rgba(79,158,255,0.3); border-radius: 8px; font-size: 13px; transition: background 0.15s; }
        .month-nav a:hover { background: var(--accent-dim); }
        .month-nav .current { font-size: 18px; font-weight: 700; font-family: 'Share Tech Mono', monospace; color: var(--text); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th { text-align: left; padding: 10px 12px; font-size: 11px; font-weight: 500; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 1px solid var(--border); }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
        tbody tr:hover { background: var(--surface2); }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 12px 12px; font-family: 'Share Tech Mono', monospace; }
        .td-accent { color: var(--accent); font-weight: 500; }
        /* フォーム */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
        .form-group label { display: block; font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 14px;
            background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
            color: var(--text); font-size: 14px; font-family: inherit; outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent); }
        .form-group select option { background: var(--surface); }
        .btn-add { margin-top: 18px; padding: 11px 28px; background: var(--accent); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; font-family: inherit; cursor: pointer; transition: opacity 0.15s; }
        .btn-add:hover { opacity: 0.85; }
        .detail-link { color: var(--accent); text-decoration: none; font-size: 12px; }
        .detail-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<nav>
    <div class="nav-brand"><span>🕐</span><?= APP_NAME ?></div>
    <div class="nav-links">
        <a href="dashboard.php">ダッシュボード</a>
        <a href="history.php">勤怠履歴</a>
        <a href="admin.php" class="active">管理者</a>
    </div>
    <div class="nav-right"><a href="logout.php">ログアウト</a></div>
</nav>
<main>
    <h2>管理者パネル</h2>
    <p style="color:var(--text-dim);font-size:13px;margin-bottom:28px;">社員管理・勤怠レポート</p>

    <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- 月選択 -->
    <?php
    $prevMonth = $month - 1; $prevYear = $year;
    if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
    $nextMonth = $month + 1; $nextYear = $year;
    if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
    ?>
    <div class="month-nav">
        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>">← 前月</a>
        <span class="current"><?= $year ?>年<?= $month ?>月</span>
        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>">次月 →</a>
    </div>

    <!-- 全社員勤怠サマリー -->
    <div class="card">
        <div class="section-title">社員別 勤怠サマリー</div>
        <table>
            <thead>
                <tr>
                    <th>社員コード</th>
                    <th>氏名</th>
                    <th>出勤日数</th>
                    <th>合計勤務時間</th>
                    <th>詳細</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td><?= htmlspecialchars($emp['employee_code']) ?></td>
                <td style="font-family:'Noto Sans JP',sans-serif;"><?= htmlspecialchars($emp['name']) ?></td>
                <td><?= $summaries[$emp['id']]['days'] ?> 日</td>
                <td class="td-accent"><?= formatMinutes($summaries[$emp['id']]['minutes']) ?></td>
                <td><a href="history.php?employee_id=<?= $emp['id'] ?>&year=<?= $year ?>&month=<?= $month ?>" class="detail-link">詳細 →</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 社員追加フォーム -->
    <div class="card">
        <div class="section-title">社員を追加</div>
        <form method="POST">
            <input type="hidden" name="action" value="add_employee">
            <div class="form-grid">
                <div class="form-group">
                    <label>社員コード</label>
                    <input type="text" name="employee_code" placeholder="EMP004" required>
                </div>
                <div class="form-group">
                    <label>氏名</label>
                    <input type="text" name="name" placeholder="田中 次郎" required>
                </div>
                <div class="form-group">
                    <label>メールアドレス</label>
                    <input type="email" name="email" placeholder="jiro@example.com" required>
                </div>
                <div class="form-group">
                    <label>パスワード</label>
                    <input type="password" name="password" placeholder="初期パスワード" required>
                </div>
                <div class="form-group">
                    <label>権限</label>
                    <select name="role">
                        <option value="employee">一般社員</option>
                        <option value="admin">管理者</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-add">＋ 社員を追加</button>
        </form>
    </div>
</main>
</body>
</html>