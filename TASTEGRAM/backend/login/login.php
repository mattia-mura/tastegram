<!-- php
// backend/login/accedi.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $sql->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(sql::FETCH_ASSOC);

        // Verifica password (funziona sia con hash che con testo in chiaro per l'ospite)
        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            
            header('Location: ../../frontend/feed.php');
            exit;
        } else {
            $error = "Username o password non corretti.";
        }
    } else {
        $error = "Compila tutti i campi.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tastegram — Accedi</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styleLogin.css">
</head>
<body>

    <div class="login-container">
        <img src="../../logo/logo_social-media.png" alt="Logo" class="logo-login">
        <h2>Bentornato!</h2>
        <p class="subtitle">Accedi per vedere cosa cucinano i tuoi amici</p>

        php if ($error): ?>
            <div class="error-box">= htmlspecialchars($error) ?></div>
        php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username o Email</label>
                <input type="text" name="username" placeholder="Inserisci il tuo username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary">Accedi ora</button>
        </form>

        <div class="divider"><span>Oppure</span></div>

        <form method="POST" action="">
            <input type="hidden" name="username" value="ospite">
            <input type="hidden" name="password" value="1234">
            <button type="submit" class="btn-guest">Entra come Ospite</button>
        </form>

        <p class="footer-text">
            Non hai un account? <a href="registration.php">Registrati gratuitamente</a>
        </p>
    </div>

</body>
</html> -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// require_once __DIR__ . '/../config/Database.php';
require_once (__DIR__ . '/../../config/Database.php');
$sql = Database::getInstance()->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $sql->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar_url'] = $user['avatar_url'];
            
            header('Location: ../../frontend/feed.php');
            exit;
        } else {
            $error = "Username o password non corretti.";
        }
    } else {
        $error = "Compila tutti i campi.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tastegram — Accedi</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles/styleLogin.css">
</head>
<body>
    <div class="login-container">
        <img src="../../img/logo_social-media.png" alt="Logo" class="logo-login">
        <h2>Bentornato!</h2>
        <p class="subtitle">Accedi per vedere i piatti degli amici</p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Il tuo username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-primary">Accedi ora</button>
        </form>

        <div class="divider"><span>Oppure</span></div>

        <a href="guest_login.php" class="btn-guest" style="display:block;text-align:center;text-decoration:none;">Entra come Ospite</a>

        <p class="footer-text">
            Nuovo qui? <a href="registration.php">Registrati gratuitamente</a>
        </p>
    </div>
</body>
</html>