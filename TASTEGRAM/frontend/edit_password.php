<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

if ($isGuest) { header('Location: feed.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $error = 'Tutti i campi sono obbligatori.';
    } elseif (strlen($newPass) < 6) {
        $error = 'La nuova password deve avere almeno 6 caratteri.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Le nuove password non coincidono.';
    } else {
        // Verifica password attuale
        $stmt = $sql->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$currentUserId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password'])) {
            $error = 'La password attuale non è corretta.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $sql->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$hashed, $currentUserId]);
            $success = 'Password aggiornata con successo!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambia Password — Tastegram</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 40px; }
        .navbar {
            position: sticky; top: 0; z-index: 100; background: #fff;
            border-bottom: 1px solid #eee; display: flex; align-items: center;
            padding: 0 16px; height: 54px; gap: 12px;
        }
        .nav-back {
            width: 34px; height: 34px; border-radius: 50%; background: var(--cr);
            border: none; cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; color: var(--tc);
        }
        .nav-title { font-size: 16px; font-weight: 700; color: var(--br); flex: 1; }
        .btn-save {
            padding: 7px 18px; background: var(--tc); color: #fff; border: none;
            border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
        }
        .form-wrap { max-width: 480px; margin: 0 auto; padding: 24px 16px; }
        .success-box {
            background: #f0fdf4; color: #166534; padding: 12px 16px;
            border-radius: 12px; font-size: 13px; margin-bottom: 20px;
            border: 1px solid #bbf7d0;
        }
        .error-box {
            background: #fff1f0; color: #d85140; padding: 12px 16px;
            border-radius: 12px; font-size: 13px; margin-bottom: 20px;
            border: 1px solid #ffa39e;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
            color: var(--br); margin-bottom: 8px;
        }
        .form-group input {
            width: 100%; padding: 13px 16px; border: 2px solid #f0f0f0;
            border-radius: 14px; font-size: 15px; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s; background: #fff; color: var(--br);
        }
        .form-group input:focus { outline: none; border-color: var(--or); background: var(--cr); }
        .hint {
            font-size: 12px; color: #bbb; margin-top: 24px; text-align: center;
            line-height: 1.5;
        }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="settings.php" class="nav-back">←</a>
    <span class="nav-title">Cambia password</span>
    <button class="btn-save" form="pw-form" type="submit">Salva</button>
</nav>

<div class="form-wrap">
    <?php if ($success): ?>
        <div class="success-box">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="pw-form" method="POST">
        <div class="form-group">
            <label>Password attuale</label>
            <input type="password" name="current_password"
                   placeholder="Inserisci la password attuale" required>
        </div>
        <div class="form-group">
            <label>Nuova password</label>
            <input type="password" name="new_password"
                   placeholder="Minimo 6 caratteri" minlength="6" required>
        </div>
        <div class="form-group">
            <label>Conferma nuova password</label>
            <input type="password" name="confirm_password"
                   placeholder="Ripeti la nuova password" required>
        </div>
    </form>

    <p class="hint">Per sicurezza ti verrà chiesto di fare il login di nuovo<br>dopo aver cambiato la password.</p>
</div>
</body>
</html>
