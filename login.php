<?php
require_once 'config.php';

// ログイン済みならダッシュボードへ
if (isset($_SESSION['employee_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM employees WHERE email = ?');
        $stmt->execute([$email]);
        $employee = $stmt->fetch();

        if ($employee && password_verify($password, $employee['password'])) {
            $_SESSION['employee_id']   = $employee['id'];
            $_SESSION['employee_name'] = $employee['name'];
            $_SESSION['employee_code'] = $employee['employee_code'];
            $_SESSION['role']          = $employee['role'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'メールアドレスまたはパスワードが正しくありません。';
        }
    } else {
        $error = 'メールアドレスとパスワードを入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - <?= APP_NAME ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Share+Tech+Mono&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0f14;
            --surface: #161920;
            --border: #252830;
            --accent: #4f9eff;
            --accent-dim: rgba(79,158,255,0.12);
            --text: #e8eaf0;
            --text-dim: #6b7280;
            --danger: #ff5f5f;
            --success: #4fdd9a;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* 背景グリッド */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(79,158,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(79,158,255,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .login-wrap {
            width: 100%;
            max-width: 420px;
            padding: 24px;
            animation: fadeUp 0.5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            width: 56px;
            height: 56px;
            background: var(--accent-dim);
            border: 1px solid rgba(79,158,255,0.3);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin-bottom: 16px;
        }

        .logo h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--text);
        }

        .logo p {
            font-size: 12px;
            color: var(--text-dim);
            margin-top: 4px;
            font-family: 'Share Tech Mono', monospace;
            letter-spacing: 0.1em;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 36px;
        }

        .error-msg {
            background: rgba(255,95,95,0.1);
            border: 1px solid rgba(255,95,95,0.3);
            color: var(--danger);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 15px;
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79,158,255,0.15);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 8px;
            letter-spacing: 0.05em;
            transition: opacity 0.2s, transform 0.1s;
        }

        .btn-login:hover { opacity: 0.88; }
        .btn-login:active { transform: scale(0.98); }

        .hint {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-dim);
            line-height: 1.7;
            font-family: 'Share Tech Mono', monospace;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="logo">
        <div class="logo-icon">🕐</div>
        <h1><?= APP_NAME ?></h1>
        <p>ATTENDANCE MANAGEMENT</p>
    </div>
    <div class="card">
        <?php if ($error): ?>
            <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="your@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">ログイン</button>
        </form>
        <div class="hint">
            テスト用: admin@example.com<br>
            パスワード: password
        </div>
    </div>
</div>
</body>
</html>