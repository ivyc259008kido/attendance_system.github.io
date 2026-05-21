<?php
require_once 'auth.php';
requireLogin();

$db = getDB();

// 管理者は任意の社員IDを指定可能
$targetId = currentEmployeeId();
if (isAdmin() && isset($_GET['employee_id'])) {
    $targetId = (int)$_GET['employee_id'];
}

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// 社員情報
$stmt = $db->prepare('SELECT * FROM employees WHERE id = ?');
$stmt->execute([$targetId]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: dashboard.php');
    exit;
}

// 勤怠記録
$stmt = $db->prepare(
    'SELECT * FROM attendance WHERE employee_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date ASC'
);
$stmt->execute([$targetId, $year, $month]);
$records = $stmt->fetchAll();

// 合計
$totalMins = 0;
$workDays = 0;
foreach ($records as $r) {
    $m = calcWorkMinutes($r['clock_in'], $r['clock_out'], $r['break_start'], $r['break_end']);
    if ($m > 0) { $totalMins += $m; $workDays++; }
}

// 月ナビ
$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$baseQuery = isAdmin() ? "&employee_id={$targetId}" : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠履歴 - <?= APP_NAME ?></title>
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
        main { position: relative; z-index: 1; max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .page-header { margin-bottom: 28px; }
        .page-header h2 { font-size: 20px; margin-bottom: 4px; }
        .page-header p { font-size: 13px; color: var(--text-dim); }
        .month-nav { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .month-nav a { color: var(--accent); text-decoration: none; padding: 6px 16px; border: 1px solid rgba(79,158,255,0.3); border-radius: 8px; font-size: 13px; transition: background 0.15s; }
        .month-nav a:hover { background: var(--accent-dim); }
        .month-nav .current { font-size: 18px; font-weight: 700; font-family: 'Share Tech Mono', monospace; }
        .stats-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-box { flex: 1; min-width: 140px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; }
        .stat-label { font-size: 11px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
        .stat-value { font-size: 24px; font-weight: 700; font-family: 'Share Tech Mono', monospace; }
        .stat-value.accent { color: var(--accent); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th { text-align: left; padding: 10px 12px; font-size: 11px; font-weight: 500; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 1px solid var(--border); }
        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
        tbody tr:hover { background: var(--surface2); }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 12px 12px; font-family: 'Share Tech Mono', monospace; }
        .td-date { color: var(--text-dim); }
        .td-accent { color: var(--accent); }
        .td-empty { color: var(--border); }
        .sat { color: #6ab0ff; }
        .sun { color: var(--danger); }
    </style>
</head>
<body>
<nav>
    <div class="nav-brand"><span>🕐</span><?= APP_NAME ?></div>
    <div class="nav-links">
        <a href="dashboard.php">ダッシュボード</a>
        <a href="history.php" class="active">勤怠履歴</a>
        <?php if (isAdmin()): ?><a href="admin.php">管理者</a><?php endif; ?>
    </div>
    <div class="nav-right"><a href="logout.php">ログアウト</a></div>
</nav>
<main>
    <div class="page-header">
        <h2>勤怠履歴</h2>
        <p><?= htmlspecialchars($employee['name']) ?> (<?= htmlspecialchars($employee['employee_code']) ?>)</p>
    </div>

    <div class="month-nav">
        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $baseQuery ?>">← 前月</a>
        <span class="current"><?= $year ?>年<?= $month ?>月</span>
        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $baseQuery ?>">次月 →</a>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">出勤日数</div>
            <div class="stat-value"><?= $workDays ?> <span style="font-size:14px;color:var(--text-dim);">日</span></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">合計勤務時間</div>
            <div class="stat-value accent"><?= formatMinutes($totalMins) ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">平均勤務時間/日</div>
            <div class="stat-value"><?= $workDays > 0 ? formatMinutes((int)($totalMins / $workDays)) : '-' ?></div>
        </div>
    </div>

    <div class="card">
        <?php if ($records): ?>
        <table>
            <thead>
                <tr>
                    <th>日付</th>
                    <th>出勤</th>
                    <th>休憩開始</th>
                    <th>休憩終了</th>
                    <th>退勤</th>
                    <th>休憩時間</th>
                    <th>勤務時間</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($records as $r):
                $mins = calcWorkMinutes($r['clock_in'], $r['clock_out'], $r['break_start'], $r['break_end']);
                $breakMins = ($r['break_start'] && $r['break_end'])
                    ? (int)((strtotime($r['break_end']) - strtotime($r['break_start'])) / 60) : 0;
                $dow = date('w', strtotime($r['date']));
                $dayClass = $dow == 0 ? 'sun' : ($dow == 6 ? 'sat' : '');
                $days = ['日','月','火','水','木','金','土'];
            ?>
            <tr>
                <td class="td-date <?= $dayClass ?>"><?= date('m/d', strtotime($r['date'])) ?> (<?= $days[$dow] ?>)</td>
                <td><?= $r['clock_in']     ? date('H:i', strtotime($r['clock_in']))     : '<span class="td-empty">--:--</span>' ?></td>
                <td><?= $r['break_start']  ? date('H:i', strtotime($r['break_start']))  : '<span class="td-empty">--:--</span>' ?></td>
                <td><?= $r['break_end']    ? date('H:i', strtotime($r['break_end']))    : '<span class="td-empty">--:--</span>' ?></td>
                <td><?= $r['clock_out']    ? date('H:i', strtotime($r['clock_out']))    : '<span class="td-empty">--:--</span>' ?></td>
                <td><?= $breakMins > 0 ? formatMinutes($breakMins) : '<span class="td-empty">-</span>' ?></td>
                <td class="<?= $mins > 0 ? 'td-accent' : 'td-empty' ?>"><?= $mins > 0 ? formatMinutes($mins) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--text-dim);font-size:13px;"><?= $year ?>年<?= $month ?>月の記録はありません。</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>