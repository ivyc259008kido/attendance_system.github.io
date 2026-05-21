<?php
require_once 'auth.php';
requireLogin();

$db         = getDB();
$employeeId = currentEmployeeId();
$today      = date('Y-m-d');
$now        = date('Y-m-d H:i:s');

// 本日の打刻データ取得
$stmt = $db->prepare('SELECT * FROM attendance WHERE employee_id = ? AND date = ?');
$stmt->execute([$employeeId, $today]);
$todayRecord = $stmt->fetch();

$message = '';
$msgType = '';

// 打刻処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clock_in') {
        if ($todayRecord) {
            $message = '本日はすでに出勤打刻済みです。';
            $msgType = 'warn';
        } else {
            $stmt = $db->prepare('INSERT INTO attendance (employee_id, date, clock_in) VALUES (?, ?, ?)');
            $stmt->execute([$employeeId, $today, $now]);
            $message = '出勤を記録しました！';
            $msgType = 'success';
        }
    } elseif ($action === 'clock_out') {
        if (!$todayRecord || !$todayRecord['clock_in']) {
            $message = '出勤打刻がありません。';
            $msgType = 'warn';
        } elseif ($todayRecord['clock_out']) {
            $message = '本日はすでに退勤打刻済みです。';
            $msgType = 'warn';
        } else {
            $stmt = $db->prepare('UPDATE attendance SET clock_out = ? WHERE employee_id = ? AND date = ?');
            $stmt->execute([$now, $employeeId, $today]);
            $message = 'お疲れさまでした！退勤を記録しました。';
            $msgType = 'success';
        }
    } elseif ($action === 'break_start') {
        if ($todayRecord && $todayRecord['clock_in'] && !$todayRecord['clock_out'] && !$todayRecord['break_start']) {
            $stmt = $db->prepare('UPDATE attendance SET break_start = ? WHERE employee_id = ? AND date = ?');
            $stmt->execute([$now, $employeeId, $today]);
            $message = '休憩開始を記録しました。';
            $msgType = 'success';
        } else {
            $message = '休憩開始を記録できません。';
            $msgType = 'warn';
        }
    } elseif ($action === 'break_end') {
        if ($todayRecord && $todayRecord['break_start'] && !$todayRecord['break_end']) {
            $stmt = $db->prepare('UPDATE attendance SET break_end = ? WHERE employee_id = ? AND date = ?');
            $stmt->execute([$now, $employeeId, $today]);
            $message = '休憩終了を記録しました。';
            $msgType = 'success';
        } else {
            $message = '休憩終了を記録できません。';
            $msgType = 'warn';
        }
    }

    // 再取得
    $stmt = $db->prepare('SELECT * FROM attendance WHERE employee_id = ? AND date = ?');
    $stmt->execute([$employeeId, $today]);
    $todayRecord = $stmt->fetch();
}

// 今月の勤怠一覧
$year  = (int)date('Y');
$month = (int)date('m');
$stmt  = $db->prepare(
    'SELECT * FROM attendance WHERE employee_id = ? AND YEAR(date) = ? AND MONTH(date) = ? ORDER BY date DESC'
);
$stmt->execute([$employeeId, $year, $month]);
$monthlyRecords = $stmt->fetchAll();

// 今月の合計勤務時間
$totalMinutes = 0;
foreach ($monthlyRecords as $r) {
    $totalMinutes += calcWorkMinutes($r['clock_in'], $r['clock_out'], $r['break_start'], $r['break_end']);
}

// 今日の勤務時間
$todayMinutes = $todayRecord
    ? calcWorkMinutes($todayRecord['clock_in'], $todayRecord['clock_out'], $todayRecord['break_start'], $todayRecord['break_end'])
    : 0;

// 現在のステータス
$status = 'off';
if ($todayRecord) {
    if (!$todayRecord['clock_in'])    $status = 'off';
    elseif ($todayRecord['clock_out']) $status = 'done';
    elseif ($todayRecord['break_start'] && !$todayRecord['break_end']) $status = 'break';
    else                               $status = 'working';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ダッシュボード - <?= APP_NAME ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Share+Tech+Mono&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0f14;
            --surface: #161920;
            --surface2: #1c2029;
            --border: #252830;
            --accent: #4f9eff;
            --accent-dim: rgba(79,158,255,0.12);
            --text: #e8eaf0;
            --text-dim: #6b7280;
            --danger: #ff5f5f;
            --success: #4fdd9a;
            --warn: #ffcc44;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(79,158,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(79,158,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* ナビ */
        nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(13,15,20,0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            height: 56px;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: 0.04em;
        }

        .nav-brand span { font-size: 20px; }

        .nav-links { display: flex; gap: 4px; }

        .nav-links a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 13px;
            padding: 6px 14px;
            border-radius: 6px;
            transition: background 0.15s, color 0.15s;
        }

        .nav-links a:hover, .nav-links a.active {
            background: var(--accent-dim);
            color: var(--accent);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--text-dim);
        }

        .nav-right a {
            color: var(--text-dim);
            text-decoration: none;
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            transition: border-color 0.15s, color 0.15s;
        }

        .nav-right a:hover { border-color: var(--accent); color: var(--accent); }

        /* メイン */
        main {
            position: relative;
            z-index: 1;
            max-width: 960px;
            margin: 0 auto;
            padding: 32px 24px;
        }

        /* メッセージ */
        .msg {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 24px;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

        .msg.success { background: rgba(79,221,154,0.1); border: 1px solid rgba(79,221,154,0.3); color: var(--success); }
        .msg.warn    { background: rgba(255,204,68,0.1);  border: 1px solid rgba(255,204,68,0.3);  color: var(--warn); }

        /* グリッド上部 */
        .top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 640px) { .top-grid { grid-template-columns: 1fr; } }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        /* 時計カード */
        .clock-card {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .clock-display {
            font-family: 'Share Tech Mono', monospace;
        }

        .clock-time {
            font-size: 48px;
            font-weight: 400;
            letter-spacing: 0.05em;
            color: var(--accent);
            line-height: 1;
        }

        .clock-date {
            font-size: 14px;
            color: var(--text-dim);
            margin-top: 6px;
        }

        /* ステータスバッジ */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 500;
        }

        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-off     { background: rgba(107,114,128,0.15); color: var(--text-dim);   border: 1px solid rgba(107,114,128,0.3); }
        .status-off::before     { background: var(--text-dim); }
        .status-working { background: rgba(79,221,154,0.12); color: var(--success);    border: 1px solid rgba(79,221,154,0.3); }
        .status-working::before { background: var(--success); animation: pulse 1.5s infinite; }
        .status-break   { background: rgba(255,204,68,0.12);  color: var(--warn);       border: 1px solid rgba(255,204,68,0.3); }
        .status-break::before   { background: var(--warn); }
        .status-done    { background: rgba(79,158,255,0.12);  color: var(--accent);     border: 1px solid rgba(79,158,255,0.3); }
        .status-done::before    { background: var(--accent); }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }

        /* 打刻ボタン群 */
        .stamp-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
            letter-spacing: 0.03em;
        }

        .btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .btn:not(:disabled):hover { opacity: 0.85; }
        .btn:not(:disabled):active { transform: scale(0.97); }

        .btn-in    { background: var(--success); color: #0d1a12; }
        .btn-out   { background: var(--danger);  color: #fff; }
        .btn-break { background: var(--warn);    color: #1a1500; }
        .btn-resume{ background: var(--accent);  color: #fff; }

        /* 統計カード */
        .stat-label {
            font-size: 11px;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Share Tech Mono', monospace;
            color: var(--text);
        }

        .stat-value.accent { color: var(--accent); }

        /* タイムライン（今日） */
        .timeline {
            display: flex;
            gap: 0;
            margin-top: 12px;
            font-size: 12px;
            font-family: 'Share Tech Mono', monospace;
            color: var(--text-dim);
        }

        .tl-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .tl-label { font-size: 10px; text-transform: uppercase; }

        .tl-time { font-size: 14px; color: var(--text); }

        .tl-sep {
            align-self: flex-end;
            padding: 0 10px;
            padding-bottom: 2px;
            color: var(--border);
        }

        /* 月間テーブル */
        .section-title {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 500;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border-bottom: 1px solid var(--border);
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }

        tbody tr:hover { background: var(--surface2); }
        tbody tr:last-child { border-bottom: none; }

        tbody td {
            padding: 12px 12px;
            font-family: 'Share Tech Mono', monospace;
            color: var(--text);
        }

        .td-date { color: var(--text-dim); }
        .td-hours { color: var(--accent); font-weight: 500; }
        .td-empty { color: var(--border); }

        /* 管理者リンク */
        .admin-banner {
            background: var(--accent-dim);
            border: 1px solid rgba(79,158,255,0.2);
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .admin-banner a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
<nav>
    <div class="nav-brand">
        <span>🕐</span><?= APP_NAME ?>
    </div>
    <div class="nav-links">
        <a href="dashboard.php" class="active">ダッシュボード</a>
        <a href="history.php">勤怠履歴</a>
        <?php if (isAdmin()): ?>
        <a href="admin.php">管理者</a>
        <?php endif; ?>
    </div>
    <div class="nav-right">
        <span><?= htmlspecialchars(currentEmployeeName()) ?></span>
        <a href="logout.php">ログアウト</a>
    </div>
</nav>

<main>
    <?php if ($message): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <div class="admin-banner">
        <span>👑 管理者権限でログイン中</span>
        <a href="admin.php">社員管理・全体レポートを見る →</a>
    </div>
    <?php endif; ?>

    <div class="top-grid">
        <!-- 時計・打刻カード -->
        <div class="card clock-card">
            <div class="clock-display">
                <div class="clock-time" id="clock">--:--:--</div>
                <div class="clock-date" id="dateStr"></div>
                <div style="margin-top:12px;">
                    <?php
                    $labels = ['off' => '未出勤', 'working' => '勤務中', 'break' => '休憩中', 'done' => '退勤済'];
                    ?>
                    <span class="status-badge status-<?= $status ?>"><?= $labels[$status] ?></span>
                </div>
            </div>
            <form method="POST">
                <div class="stamp-buttons">
                    <button type="submit" name="action" value="clock_in"  class="btn btn-in"
                        <?= ($status !== 'off') ? 'disabled' : '' ?>>出勤</button>
                    <button type="submit" name="action" value="break_start" class="btn btn-break"
                        <?= ($status !== 'working') ? 'disabled' : '' ?>>休憩開始</button>
                    <button type="submit" name="action" value="break_end" class="btn btn-resume"
                        <?= ($status !== 'break') ? 'disabled' : '' ?>>休憩終了</button>
                    <button type="submit" name="action" value="clock_out" class="btn btn-out"
                        <?= (!in_array($status, ['working'])) ? 'disabled' : '' ?>>退勤</button>
                </div>
            </form>
        </div>

        <!-- 今日の記録 -->
        <div class="card">
            <div class="stat-label">本日の勤務時間</div>
            <div class="stat-value accent"><?= formatMinutes($todayMinutes) ?></div>
            <?php if ($todayRecord): ?>
            <div class="timeline" style="margin-top:16px;">
                <?php if ($todayRecord['clock_in']): ?>
                <div class="tl-item">
                    <div class="tl-label">出勤</div>
                    <div class="tl-time"><?= date('H:i', strtotime($todayRecord['clock_in'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($todayRecord['break_start']): ?>
                <div class="tl-sep">→</div>
                <div class="tl-item">
                    <div class="tl-label">休憩</div>
                    <div class="tl-time"><?= date('H:i', strtotime($todayRecord['break_start'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($todayRecord['break_end']): ?>
                <div class="tl-sep">→</div>
                <div class="tl-item">
                    <div class="tl-label">再開</div>
                    <div class="tl-time"><?= date('H:i', strtotime($todayRecord['break_end'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($todayRecord['clock_out']): ?>
                <div class="tl-sep">→</div>
                <div class="tl-item">
                    <div class="tl-label">退勤</div>
                    <div class="tl-time"><?= date('H:i', strtotime($todayRecord['clock_out'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p style="margin-top:12px;font-size:13px;color:var(--text-dim);">本日の打刻記録はありません。</p>
            <?php endif; ?>
        </div>

        <!-- 今月の統計 -->
        <div class="card">
            <div class="stat-label">今月の合計勤務時間</div>
            <div class="stat-value"><?= formatMinutes($totalMinutes) ?></div>
            <div style="margin-top:16px;font-size:13px;color:var(--text-dim);">
                出勤日数: <span style="color:var(--text);font-family:'Share Tech Mono',monospace;">
                    <?= count(array_filter($monthlyRecords, fn($r) => $r['clock_in'])) ?> 日
                </span>
            </div>
        </div>
    </div>

    <!-- 今月の勤怠一覧 -->
    <div class="card">
        <div class="section-title"><?= $year ?>年<?= $month ?>月 勤怠記録</div>
        <?php if ($monthlyRecords): ?>
        <table>
            <thead>
                <tr>
                    <th>日付</th>
                    <th>出勤</th>
                    <th>退勤</th>
                    <th>休憩</th>
                    <th>勤務時間</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($monthlyRecords as $r):
                $mins  = calcWorkMinutes($r['clock_in'], $r['clock_out'], $r['break_start'], $r['break_end']);
                $breakMins = ($r['break_start'] && $r['break_end'])
                    ? (strtotime($r['break_end']) - strtotime($r['break_start'])) / 60 : 0;
            ?>
            <tr>
                <td class="td-date"><?= date('m/d (D)', strtotime($r['date'])) ?></td>
                <td><?= $r['clock_in']  ? date('H:i', strtotime($r['clock_in']))  : '<span class="td-empty">--:--</span>' ?></td>
                <td><?= $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : '<span class="td-empty">--:--</span>' ?></td>
                <td><?= $breakMins > 0 ? formatMinutes((int)$breakMins) : '<span class="td-empty">-</span>' ?></td>
                <td class="<?= $mins > 0 ? 'td-hours' : 'td-empty' ?>"><?= $mins > 0 ? formatMinutes($mins) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="color:var(--text-dim);font-size:13px;">今月の記録はありません。</p>
        <?php endif; ?>
    </div>
</main>

<script>
function updateClock() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    document.getElementById('clock').textContent =
        `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    const days = ['日','月','火','水','木','金','土'];
    document.getElementById('dateStr').textContent =
        `${now.getFullYear()}年${now.getMonth()+1}月${now.getDate()}日 (${days[now.getDay()]})`;
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>