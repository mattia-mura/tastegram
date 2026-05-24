<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$targetUsername = $_GET['user'] ?? $currentUsername;

$stmt = $sql->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$targetUsername]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    die('<p style="text-align:center;margin-top:60px;font-family:sans-serif">Utente non trovato.</p>');
}

$isOwnProfile = ($profile['id'] === $currentUserId);

$followStmt = $sql->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
$followStmt->execute([$currentUserId, $profile['id']]);
$isFollowing = (bool) $followStmt->fetchColumn();

$postStmt = $sql->prepare("
    SELECT id, title_work, image_path, likes_count, comments_count
    FROM posts WHERE user_id = ?
    ORDER BY created_at DESC
");
$postStmt->execute([$profile['id']]);
$userPosts = $postStmt->fetchAll();

$avatar = $profile['avatar_url'] ?: 'default_avatar.png';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?= htmlspecialchars($profile['username']) ?> — Tastegram</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 70px; }

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
        .nav-username { font-size: 16px; font-weight: 700; color: var(--br); flex: 1; }
        .nav-settings {
            width: 34px; height: 34px; border-radius: 50%; background: var(--cr);
            display: flex; align-items: center; justify-content: center;
            text-decoration: none; font-size: 18px; color: var(--br);
        }

        .profile-wrap { max-width: 480px; margin: 0 auto; }
        .profile-header { background: #fff; padding: 20px 16px 16px; border-bottom: 1px solid #f0f0f0; }
        .profile-top { display: flex; align-items: center; gap: 20px; margin-bottom: 14px; }
        .profile-avatar-wrap {
            width: 86px; height: 86px; border-radius: 50%;
            overflow: hidden; border: 3px solid var(--or); flex-shrink: 0;
        }
        .profile-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .profile-stats { display: flex; flex: 1; justify-content: space-around; }
        .stat-col { display: flex; flex-direction: column; align-items: center; gap: 2px; }
        .stat-num { font-size: 18px; font-weight: 700; color: var(--br); }
        .stat-lbl { font-size: 11px; color: #999; }
        .profile-info { margin-bottom: 14px; }
        .profile-name { font-size: 15px; font-weight: 700; color: var(--br); margin-bottom: 4px; }
        .profile-bio { font-size: 13px; color: #555; line-height: 1.5; }
        .profile-bio-empty { font-size: 13px; color: #bbb; font-style: italic; }

        .profile-actions { display: flex; }
        .btn-action {
            width: 100%; padding: 10px 12px; border-radius: 10px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            border: 1.5px solid; font-family: 'DM Sans', sans-serif;
            transition: all .2s; text-align: center; text-decoration: none;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-follow-action { background: var(--tc); border-color: var(--tc); color: #fff; }
        .btn-follow-action.following { background: #f0f0f0; border-color: #ddd; color: #555; }
        .btn-follow-action:hover { opacity: .88; }

        .post-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; padding: 2px; }
        .grid-item {
            aspect-ratio: 1/1; overflow: hidden; position: relative;
            cursor: pointer; background: var(--cr);
            display: flex; align-items: center; justify-content: center;
        }
        .grid-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .grid-placeholder { font-size: 36px; }
        .grid-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0); color: #fff;
            display: flex; align-items: center; justify-content: center;
            gap: 14px; font-size: 13px; font-weight: 700; transition: background .2s;
        }
        .grid-item:hover .grid-overlay { background: rgba(0,0,0,0.38); }
        .empty-grid { grid-column: 1/-1; padding: 60px 24px; text-align: center; color: #bbb; }
        .empty-grid .emoji { font-size: 44px; margin-bottom: 12px; }

        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; height: 58px;
            background: #fff; border-top: 1px solid #eee; display: flex; z-index: 100;
        }
        .bn-item {
            flex: 1; display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 2px; text-decoration: none; color: #aaa;
            border: none; background: none; cursor: pointer; font-family: 'DM Sans', sans-serif;
        }
        .bn-item .bn-icon { font-size: 22px; }
        .bn-item .bn-label { font-size: 10px; }
        .bn-item.active { color: var(--tc); }
        .bn-item.active .bn-label { font-weight: 700; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="feed.php" class="nav-back">←</a>
    <span class="nav-username">@<?= htmlspecialchars($profile['username']) ?></span>
    <?php if ($isOwnProfile): ?>
        <a href="settings.php" class="nav-settings" title="Impostazioni">⚙️</a>
    <?php else: ?>
        <div style="width:34px"></div>
    <?php endif; ?>
</nav>

<div class="profile-wrap">
    <div class="profile-header">
        <div class="profile-top">
            <div class="profile-avatar-wrap">
                <img src="<?= htmlspecialchars(avatarSrc($avatar)) ?>"
                     onerror="this.src='../img/default_avatar.png'"
                     alt="@<?= htmlspecialchars($profile['username']) ?>">
            </div>
            <div class="profile-stats">
                <div class="stat-col">
                    <span class="stat-num"><?= count($userPosts) ?></span>
                    <span class="stat-lbl">Post</span>
                </div>
                <div class="stat-col">
                    <span class="stat-num" id="followers-count"><?= number_format($profile['followers_count']) ?></span>
                    <span class="stat-lbl">Follower</span>
                </div>
                <div class="stat-col">
                    <span class="stat-num"><?= number_format($profile['following_count']) ?></span>
                    <span class="stat-lbl">Seguiti</span>
                </div>
            </div>
        </div>

        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($profile['username']) ?></div>
            <?php if (!empty($profile['bio'])): ?>
                <div class="profile-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></div>
            <?php else: ?>
                <div class="profile-bio-empty">
                    <?= $isOwnProfile ? 'Aggiungi una bio dalle impostazioni ⚙️' : 'Nessuna bio.' ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$isOwnProfile): ?>
        <div class="profile-actions">
            <?php if (!$isGuest): ?>
                <button class="btn-action btn-follow-action <?= $isFollowing ? 'following' : '' ?>"
                        id="follow-btn"
                        onclick="toggleFollow(<?= $profile['id'] ?>)">
                    <?= $isFollowing ? '✓ Seguito' : '+ Segui' ?>
                </button>
            <?php else: ?>
                <a href="../backend/login/registration.php"
                   class="btn-action btn-follow-action">+ Segui</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- Nessun bottone "Modifica profilo" qui: si accede solo da ⚙️ → settings.php -->
    </div>

    <div class="post-grid">
        <?php if (empty($userPosts)): ?>
            <div class="empty-grid">
                <div class="emoji">📷</div>
                <p><?= $isOwnProfile ? 'Non hai ancora pubblicato nulla.' : 'Nessun post ancora.' ?></p>
                <?php if ($isOwnProfile && !$isGuest): ?>
                    <a href="new_post.php" style="display:inline-block;margin-top:16px;padding:10px 24px;
                       background:var(--tc);color:#fff;border-radius:12px;text-decoration:none;font-weight:600;">
                        + Pubblica ora
                    </a>
                <?php endif; ?>
            </div>
        <?php else: foreach ($userPosts as $p): ?>
            <div class="grid-item" onclick="window.location='post.php?id=<?= $p['id'] ?>'">
                <?php if (!empty($p['image_path'])): ?>
                    <img src="<?= htmlspecialchars(postImageSrc($p['image_path'])) ?>"
                         onerror="this.style.display='none'"
                         alt="<?= htmlspecialchars($p['title_work']) ?>">
                <?php else: ?>
                    <div class="grid-placeholder">🍽️</div>
                <?php endif; ?>
                <div class="grid-overlay">
                    <span>❤️ <?= $p['likes_count'] ?></span>
                    <span>💬 <?= $p['comments_count'] ?></span>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<nav class="bottom-nav">
    <a href="feed.php" class="bn-item"><span class="bn-icon">🏠</span><span class="bn-label">Home</span></a>
    <a href="explore.php" class="bn-item"><span class="bn-icon">🔍</span><span class="bn-label">Esplora</span></a>
    <?php if (!$isGuest): ?>
    <a href="new_post.php" class="bn-item">
        <span class="bn-icon" style="font-size:30px;color:var(--tc)">＋</span>
        <span class="bn-label">Pubblica</span>
    </a>
    <?php endif; ?>
    <a href="notifications.php" class="bn-item"><span class="bn-icon">🔔</span><span class="bn-label">Notifiche</span></a>
    <a href="profile.php?user=<?= urlencode($currentUsername) ?>"
       class="bn-item <?= $isOwnProfile ? 'active' : '' ?>">
        <span class="bn-icon">👤</span><span class="bn-label">Profilo</span>
    </a>
</nav>

<script>
function toggleFollow(targetId) {
    const btn = document.getElementById('follow-btn');

    // Blocca click multipli durante la chiamata
    if (btn.disabled) return;
    btn.disabled = true;
    btn.style.opacity = '0.6';

    fetch('/tastegram/backend/api/follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_id: targetId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.following ? '✓ Seguito' : '+ Segui';
            btn.classList.toggle('following', data.following);
            document.getElementById('followers-count').textContent = data.followers_count;
        } else {
            alert('Errore: ' + (data.error ?? 'sconosciuto'));
        }
    })
    .catch(() => alert('Errore di rete, riprova.'))
    .finally(() => {
        // Riabilita il bottone solo dopo la risposta
        btn.disabled = false;
        btn.style.opacity = '1';
    });
}
</script>
</body>
</html>
