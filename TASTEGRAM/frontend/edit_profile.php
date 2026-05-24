<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

if ($isGuest) { header('Location: feed.php'); exit; }

$stmt = $sql->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username'] ?? '');
    $newBio      = trim($_POST['bio']      ?? '');
    $newEmail    = trim($_POST['email']    ?? '');
    $newAvatar   = $user['avatar_url'];

    if (empty($newUsername)) {
        $error = 'Il nome utente non può essere vuoto.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $newUsername)) {
        $error = 'Username: solo lettere, numeri, punti e underscore.';
    } elseif (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Inserisci un indirizzo email valido.';
    } else {
        $check = $sql->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check->execute([$newUsername, $newEmail, $currentUserId]);
        if ($check->fetch()) {
            $error = 'Username o email già in uso.';
        } else {

            // ── UPLOAD AVATAR ──
            if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file     = $_FILES['avatar'];
                $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if ($file['size'] > 2 * 1024 * 1024) {
                    $error = 'Immagine troppo grande. Massimo 2MB.';
                } elseif (!in_array($mimeType, $allowed)) {
                    $error = 'Formato non supportato. Usa JPG, PNG o WebP.';
                } else {
                    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'avatar_' . $currentUserId . '_' . time() . '.' . $ext;

                    // Path assoluto robusto su XAMPP
                    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/tastegram/img/uploads/avatars/';

                    // Crea cartella se non esiste
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        // Elimina vecchio avatar se non è il default
                        if ($user['avatar_url'] !== 'default_avatar.png') {
                            $oldFile = $uploadDir . $user['avatar_url'];
                            if (file_exists($oldFile)) unlink($oldFile);
                        }
                        $newAvatar = $filename;
                    } else {
                        $error = 'Errore caricamento. Verifica i permessi della cartella img/uploads/avatars/';
                    }
                }
            } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                $phpErrors = [
                    UPLOAD_ERR_INI_SIZE  => 'File troppo grande (php.ini).',
                    UPLOAD_ERR_FORM_SIZE => 'File troppo grande.',
                    UPLOAD_ERR_PARTIAL   => 'Upload incompleto.',
                    UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco.',
                ];
                $error = $phpErrors[$_FILES['avatar']['error']] ?? 'Errore upload.';
            }

            if (empty($error)) {
                $sql->prepare("UPDATE users SET username=?, email=?, bio=?, avatar_url=? WHERE id=?")
                    ->execute([$newUsername, $newEmail, $newBio, $newAvatar, $currentUserId]);

                // Aggiorna sessione
                $_SESSION['username']   = $newUsername;
                $_SESSION['avatar_url'] = $newAvatar;
                $currentUsername = $newUsername;
                $currentAvatar   = $newAvatar;

                $success = 'Profilo aggiornato con successo!';

                // Ricarica dati aggiornati dal DB
                $stmt = $sql->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$currentUserId]);
                $user = $stmt->fetch();
            }
        }
    }
}

// Timestamp per cache busting sull'avatar
$avatarTs = time();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifica Profilo — Tastegram</title>
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
        .form-wrap { max-width: 480px; margin: 0 auto; padding: 20px 16px; }
        .success-box {
            background: #f0fdf4; color: #166534; padding: 12px 16px;
            border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid #bbf7d0;
        }
        .error-box {
            background: #fff1f0; color: #d85140; padding: 12px 16px;
            border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid #ffa39e;
        }
        .avatar-section { display: flex; flex-direction: column; align-items: center; margin-bottom: 28px; }
        .avatar-wrap {
            width: 96px; height: 96px; border-radius: 50%; overflow: hidden;
            border: 3px solid var(--or); position: relative; cursor: pointer; margin-bottom: 8px;
        }
        .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .avatar-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.4);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity .2s; font-size: 26px;
        }
        .avatar-wrap:hover .avatar-overlay { opacity: 1; }
        .avatar-wrap input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; font-size: 0; }
        .avatar-hint { font-size: 12px; color: #999; }
        .section-title {
            font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #bbb; margin-bottom: 14px;
            padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: var(--br); margin-bottom: 8px;
        }
        .form-group input, .form-group textarea {
            width: 100%; padding: 13px 16px; border: 2px solid #f0f0f0;
            border-radius: 14px; font-size: 15px; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s; background: #fff; color: var(--br);
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: var(--or); background: var(--cr);
        }
        .form-group textarea { height: 100px; resize: none; line-height: 1.6; }
        .change-pw-link {
            display: block; text-align: center; margin-top: 24px; padding: 12px;
            border-radius: 12px; border: 1.5px solid #eee; color: #666;
            text-decoration: none; font-size: 14px; font-weight: 500; transition: all .2s;
        }
        .change-pw-link:hover { background: var(--cr); border-color: var(--or); color: var(--tc); }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="settings.php" class="nav-back">←</a>
    <span class="nav-title">Modifica profilo</span>
    <button class="btn-save" form="edit-form" type="submit">Salva</button>
</nav>

<div class="form-wrap">
    <?php if ($success): ?>
        <div class="success-box">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="edit-form" method="POST" enctype="multipart/form-data">

        <!-- Avatar con cache busting sul src -->
        <div class="avatar-section">
            <div class="avatar-wrap">
                <?php
                $avatarFile = $user['avatar_url'] ?: 'default_avatar.png';
                // Se è il default sta in /img/, altrimenti in /img/uploads/avatars/
                $avatarSrc = ($avatarFile === 'default_avatar.png')
                    ? '../img/default_avatar.png'
                    : '../img/uploads/avatars/' . $avatarFile . '?v=' . $avatarTs;
                ?>
                <img id="avatar-preview"
                     src="<?= htmlspecialchars($avatarSrc) ?>"
                     onerror="this.src='../img/default_avatar.png'"
                     alt="Avatar">
                <div class="avatar-overlay">📷</div>
                <input type="file" name="avatar" id="avatar-input"
                       accept="image/jpeg,image/png,image/webp">
            </div>
            <span class="avatar-hint">Tocca per cambiare la foto profilo · max 2MB</span>
        </div>

        <div class="section-title">Informazioni profilo</div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username"
                   value="<?= htmlspecialchars($user['username']) ?>"
                   maxlength="50" required>
        </div>
        <div class="form-group">
            <label>Bio</label>
            <textarea name="bio" maxlength="300"
                      placeholder="Racconta qualcosa di te e della tua cucina..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>

        <div class="section-title" style="margin-top:24px">Email</div>
        <div class="form-group">
            <label>Indirizzo email</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
    </form>

    <a href="edit_password.php" class="change-pw-link">
        🔒 Cambia password
    </a>
</div>

<script>
document.getElementById('avatar-input').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    // Anteprima immediata senza ricaricare la pagina
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('avatar-preview').src = e.target.result;
    };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>
