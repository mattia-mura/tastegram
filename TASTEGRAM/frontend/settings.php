<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$stmt = $sql->prepare("SELECT username, email, avatar_url FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$me = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impostazioni — Tastegram</title>
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
        .nav-title { font-size: 16px; font-weight: 700; color: var(--br); }

        .settings-wrap { max-width: 480px; margin: 0 auto; }

        .profile-preview {
            background: #fff; padding: 20px 16px;
            display: flex; align-items: center; gap: 14px;
            border-bottom: 1px solid #f0f0f0;
        }
        .preview-avatar {
            width: 56px; height: 56px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or);
        }
        .preview-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .preview-info { flex: 1; }
        .preview-username { font-size: 15px; font-weight: 700; color: var(--br); }
        .preview-email { font-size: 12px; color: #999; margin-top: 2px; }

        .settings-group { margin-top: 12px; background: #fff; }
        .group-label {
            padding: 10px 16px 6px; font-size: 11px; font-weight: 700;
            color: #bbb; text-transform: uppercase; letter-spacing: .5px;
        }
        .settings-item {
            display: flex; align-items: center; gap: 14px; padding: 14px 16px;
            border-bottom: 1px solid #f5f5f5; text-decoration: none; color: inherit;
            transition: background .15s;
        }
        .settings-item:hover { background: var(--cr); }
        .settings-item:last-child { border-bottom: none; }
        .item-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .item-text { flex: 1; }
        .item-label { font-size: 14px; font-weight: 500; color: var(--br); }
        .item-desc { font-size: 12px; color: #aaa; margin-top: 2px; }
        .item-arrow { color: #ccc; font-size: 18px; }

        /* Logout: separato visivamente con margine e colore diverso */
        .logout-group {
            margin-top: 24px; background: #fff;
            border-top: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0;
        }
        .logout-btn {
            display: flex; align-items: center; gap: 14px; padding: 14px 16px;
            width: 100%; background: none; border: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: background .15s;
        }
        .logout-btn:hover { background: #fff1f0; }
        .logout-btn .item-label { color: #d85140; font-weight: 600; }

        .version { text-align: center; padding: 24px 16px; font-size: 12px; color: #ccc; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="profile.php?user=<?= urlencode($currentUsername) ?>" class="nav-back">←</a>
    <span class="nav-title">Impostazioni</span>
</nav>

<div class="settings-wrap">

    <!-- Anteprima profilo -->
    <div class="profile-preview">
        <div class="preview-avatar">
            <img src="<?= htmlspecialchars(avatarSrc($me['avatar_url'] ?? 'default_avatar.png')) ?>"
                 onerror="this.src='../img/default_avatar.png'" alt="avatar">
        </div>
        <div class="preview-info">
            <div class="preview-username">@<?= htmlspecialchars($me['username']) ?></div>
            <div class="preview-email"><?= htmlspecialchars($me['email']) ?></div>
        </div>
    </div>

    <?php if (!$isGuest): ?>

    <!-- ACCOUNT: solo per utenti registrati -->
    <div class="settings-group">
        <div class="group-label">Account</div>
        <a href="edit_profile.php" class="settings-item">
            <div class="item-icon" style="background:#fdf0e0">👤</div>
            <div class="item-text">
                <div class="item-label">Modifica profilo</div>
                <div class="item-desc">Username, bio e foto profilo</div>
            </div>
            <span class="item-arrow">›</span>
        </a>
        <a href="edit_password.php" class="settings-item">
            <div class="item-icon" style="background:#fdf0e0">🔒</div>
            <div class="item-text">
                <div class="item-label">Cambia password</div>
                <div class="item-desc">Aggiorna le credenziali di accesso</div>
            </div>
            <span class="item-arrow">›</span>
        </a>
    </div>

    <!-- CONTENUTI -->
    <div class="settings-group">
        <div class="group-label">Contenuti</div>
        <a href="profile.php?user=<?= urlencode($currentUsername) ?>" class="settings-item">
            <div class="item-icon" style="background:#f0fdf4">📷</div>
            <div class="item-text">
                <div class="item-label">I miei post</div>
                <div class="item-desc">Visualizza e gestisci i tuoi piatti</div>
            </div>
            <span class="item-arrow">›</span>
        </a>
        <a href="notifications.php" class="settings-item">
            <div class="item-icon" style="background:#fff8f0">🔔</div>
            <div class="item-text">
                <div class="item-label">Notifiche</div>
                <div class="item-desc">Like, commenti e nuovi follower</div>
            </div>
            <span class="item-arrow">›</span>
        </a>
    </div>

    <?php else: ?>

    <!-- OSPITE: messaggio informativo -->
    <div class="settings-group">
        <div class="group-label">Account ospite</div>
        <div class="settings-item" style="cursor:default;">
            <div class="item-icon" style="background:#f5f5f5">🔒</div>
            <div class="item-text">
                <div class="item-label" style="color:#aaa;">Funzioni non disponibili</div>
                <div class="item-desc">Registrati per modificare il profilo e accedere a tutte le funzioni</div>
            </div>
        </div>
        <a href="../backend/login/registration.php" class="settings-item">
            <div class="item-icon" style="background:#fdf0e0">✏️</div>
            <div class="item-text">
                <div class="item-label">Crea un account</div>
                <div class="item-desc">Registrati gratuitamente</div>
            </div>
            <span class="item-arrow">›</span>
        </a>
    </div>

    <?php endif; ?>

    <!-- LOGOUT separato — ben distanziato dalla sezione account -->
    <div class="logout-group">
        <button class="logout-btn" onclick="confirmLogout()">
            <div class="item-icon" style="background:#fff1f0">🚪</div>
            <div class="item-text">
                <div class="item-label">Esci dall'account</div>
            </div>
        </button>
    </div>

    <div class="version">Tastegram v1.0</div>
</div>

<script>
function confirmLogout() {
    if (confirm('Sei sicuro di voler uscire?')) {
        window.location.href = '../backend/login/logout.php';
    }
}
</script>
</body>
</html>
