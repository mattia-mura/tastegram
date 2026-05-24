<!-- 
php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// require_once __DIR__ . '/../config/Database.php';
require_once (__DIR__ . '/../../config/Database.php');
$sql = Database::getInstance()->getConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($email) && !empty($password)) {
        $check = $sql->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        
        if ($check->rowCount() > 0) {
            $error = "Username o Email già in uso.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $sql->prepare("INSERT INTO users (username, email, password, avatar_url) VALUES (?, ?, ?, 'default_avatar.png')");
            
            if ($stmt->execute([$username, $email, $hashedPassword])) {
                $_SESSION['user_id'] = $sql->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['avatar_url'] = 'default_avatar.png';
                
                header('Location: ../../frontend/feed.php');
                exit;
            } else {
                $error = "Errore durante la registrazione.";
            }
        }
    } else {
        $error = "Tutti i campi sono obbligatori.";
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tastegram — Registrati</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles/styleLogin.css">
</head>
<body>
    <div class="login-container">
        <img src="../../img/logo_social-media.png" alt="Logo" class="logo-login">
        <h2>Crea account</h2>
        <p class="subtitle">Inizia la tua avventura culinaria</p>

        php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Scegli un username" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="latua@email.com" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Minimo 6 caratteri" required>
            </div>
            <button type="submit" class="btn-primary">Registrati</button>
        </form>

        <p class="footer-text">
            Hai già un account? <a href="login.php">Accedi</a>
        </p>
    </div>
</body>
</html> -->
<!-- quando uno crea account di default img=>default_avatr.png -->
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
 
require_once (__DIR__ . '/../../config/Database.php');
$sql = Database::getInstance()->getConnection();
 
$error = '';
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
 
    // if x password->restrizioni
    if (empty($username) || empty($email) || empty($password)) { //vuoto
        $error = 'Tutti i campi sono obbligatori.';
    } elseif (strlen($username) < 4 && strlen($username) > 20) { //4-20 caratteri usr
        $error = 'Username deve avere almeno 4 caratteri e massimo 20.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) { //caretteri concesi
        $error = 'Username: solo lettere, numeri, punti e underscore.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { //@ e .
        $error = 'Inserisci un indirizzo email valido.';
    } elseif (strlen($password) < 6 && strlen($password) > 20) { //6-20 psswd
        $error = 'La password deve avere almeno 6 caratteri e massimo 20.';
    } else {
        //controlla username e email nel db
        $check = $sql->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
 
        if ($check->fetch()) {
            $error = 'Username o email già in uso.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
 
            $stmt = $sql->prepare("
                INSERT INTO users (username, email, password, avatar_url)
                VALUES (?, ?, ?, 'default_avatar.png')
            ");
 
            if ($stmt->execute([$username, $email, $hashedPassword])) {
                $_SESSION['user_id']    = (int) $sql->lastInsertId();
                $_SESSION['username']   = $username;
                $_SESSION['avatar_url'] = 'default_avatar.png';
                header('Location: ../../frontend/feed.php');
                exit;
            } else {
                $error = 'Errore durante la registrazione. Riprova.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tastegram — Registrati</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles/styleLogin.css">
</head>
<body>
    <div class="login-container">
        <img src="../../img/logo_social-media.png" alt="Logo" class="logo-login"
             onerror="this.style.display='none'">
        <h2>Crea account</h2>
        <p class="subtitle">Inizia la tua avventura culinaria 🍴</p>
 
        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
 
        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username"
                       placeholder="Scegli un username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email"
                       placeholder="latua@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="Minimo 6 caratteri"
                       required>
            </div>
            <button type="submit" class="btn-primary">Registrati</button>
        </form>
 
        <p class="footer-text">
            Hai già un account? <a href="login.php">Accedi</a>
        </p>
    </div>
</body>
</html>
