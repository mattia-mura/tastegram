<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$filter = $_GET['filter'] ?? 'all';

if ($filter === 'friends') {
    $stmt = $sql->prepare("
        SELECT p.*, u.username, u.avatar_url
        FROM posts p
        JOIN users u ON p.user_id = u.id
        JOIN follows f ON f.followed_id = p.user_id
        WHERE f.follower_id = :uid
        ORDER BY p.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([':uid' => $currentUserId]);
} else {
    $stmt = $sql->prepare("
        SELECT p.*, u.username, u.avatar_url
        FROM posts p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 30
    ");
    $stmt->execute();
}
$posts = $stmt->fetchAll();

function userLikedPost(PDO $sql, int $postId, int $userId): bool {
    $s = $sql->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $s->execute([$postId, $userId]);
    return (bool) $s->fetchColumn();
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'ora';
    if ($diff < 3600)   return floor($diff / 60) . 'm';
    if ($diff < 86400)  return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'g';
    return date('d/m/Y', strtotime($datetime));
}

// Notifiche non lette
$nStmt = $sql->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$nStmt->execute([$currentUserId]);
$unreadNotifs = (int) $nStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tastegram — Home</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --am:#F0882A; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 70px; }

        .navbar {
            position: sticky; top: 0; z-index: 100; background: #fff;
            border-bottom: 1px solid #eee; display: flex; align-items: center;
            padding: 0 16px; height: 54px; gap: 12px;
        }
        .navbar-logo { font-size: 22px; font-weight: 700; color: var(--tc); flex: 1;
            letter-spacing: -0.5px; text-decoration: none; }
        .navbar-icon {
            width: 34px; height: 34px; border-radius: 50%; background: var(--cr);
            border: none; cursor: pointer; display: flex; align-items: center;
            justify-content: center; font-size: 16px; text-decoration: none;
            color: var(--tc); position: relative;
        }
        .notif-dot {
            width: 8px; height: 8px; border-radius: 50%; background: var(--tc);
            position: absolute; top: 4px; right: 4px;
        }
        /* Avatar navbar — usa uploads/avatars se non è default */
        .navbar-avatar {
            width: 34px; height: 34px; border-radius: 50%; overflow: hidden;
            border: 2px solid var(--or); cursor: pointer; flex-shrink: 0;
            text-decoration: none;
        }
        .navbar-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .filter-bar {
            background: #fff; padding: 10px 16px; border-bottom: 1px solid #eee;
            display: flex; gap: 8px; position: sticky; top: 54px; z-index: 99;
        }
        .filter-btn {
            padding: 6px 18px; border-radius: 20px; font-size: 13px; font-weight: 500;
            cursor: pointer; border: 1.5px solid; transition: all .2s;
            text-decoration: none;
        }
        .filter-btn.active { background: var(--tc); border-color: var(--tc); color: #fff; }
        .filter-btn:not(.active) { background: transparent; border-color: #ddd; color: #666; }

        .feed { max-width: 480px; margin: 0 auto; }
        .post-card { background: #fff; margin-bottom: 1px; border-bottom: 1px solid #f0f0f0; }
        .post-header { display: flex; align-items: center; gap: 10px; padding: 12px 14px 8px; }
        .post-avatar {
            width: 38px; height: 38px; border-radius: 50%; overflow: hidden;
            border: 2px solid var(--or); flex-shrink: 0;
        }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .post-username { font-size: 14px; font-weight: 700; color: var(--br); text-decoration: none; }
        .post-username:hover { text-decoration: underline; }
        .post-meta { font-size: 11px; color: #999; margin-top: 2px; }
        .post-time { margin-left: auto; font-size: 11px; color: #bbb; white-space: nowrap; }

        .post-image { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; background: var(--cr); }
        .post-image-placeholder {
            width: 100%; aspect-ratio: 1/1; background: linear-gradient(135deg,#f5e6d3,#fce8d0);
            display: flex; align-items: center; justify-content: center; font-size: 64px;
        }

        .post-actions { display: flex; align-items: center; gap: 14px; padding: 10px 14px 4px; }
        .action-btn {
            background: none; border: none; cursor: pointer; display: flex; align-items: center;
            gap: 5px; font-size: 13px; color: #666; font-family: 'DM Sans', sans-serif; padding: 0;
        }
        .action-btn .icon { font-size: 22px; transition: transform .15s; }
        .action-btn:hover .icon { transform: scale(1.15); }
        .action-btn.liked .icon { color: var(--tc); }
        .stars-row { display: flex; gap: 1px; margin-left: auto; }

        .post-likes { padding: 0 14px 4px; font-size: 13px; font-weight: 700; color: var(--br); }
        .post-caption { padding: 2px 14px 12px; font-size: 13px; color: #333; line-height: 1.5; }
        .post-caption .author { font-weight: 700; color: var(--br); }

        .empty-state { text-align: center; padding: 60px 24px; color: #bbb; }
        .empty-state .emoji { font-size: 48px; margin-bottom: 12px; }

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

        .guest-banner {
            background: var(--cr); border-bottom: 1px solid #f0d9c0;
            padding: 8px 16px; text-align: center; font-size: 12px; color: var(--br);
        }
        .guest-banner a { color: var(--tc); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="feed.php" class="navbar-logo">tastegram</a>

    <?php if (!$isGuest): ?>
    <a href="notifications.php" class="navbar-icon">
        🔔
        <?php if ($unreadNotifs > 0): ?>
            <div class="notif-dot"></div>
        <?php endif; ?>
    </a>
    <?php endif; ?>

    <!-- Avatar navbar: usa avatarSrc() per il path corretto -->
    <a href="profile.php?user=<?= urlencode($currentUsername) ?>" class="navbar-avatar">
        <img src="<?= htmlspecialchars(avatarSrc($currentAvatar)) ?>"
             onerror="this.src='../img/default_avatar.png'"
             alt="<?= htmlspecialchars($currentUsername) ?>">
    </a>
</nav>

<?php if ($isGuest): ?>
<div class="guest-banner">
    Stai navigando come ospite —
    <a href="../backend/login/registration.php">Registrati</a> per pubblicare e seguire!
</div>
<?php endif; ?>

<div class="filter-bar">
    <a href="feed.php?filter=all"
       class="filter-btn <?= $filter === 'all' ? 'active' : '' ?>">🌍 Tutti</a>
    <a href="feed.php?filter=friends"
       class="filter-btn <?= $filter === 'friends' ? 'active' : '' ?>">👥 Amici</a>
</div>

<div class="feed">
<?php if (empty($posts)): ?>
    <div class="empty-state">
        <div class="emoji"><?= $filter === 'friends' ? '👥' : '🍽️' ?></div>
        <p><?= $filter === 'friends'
            ? 'Non segui ancora nessuno. Scopri nuovi chef!'
            : 'Nessun post ancora. Sii il primo a condividere!' ?></p>
    </div>

<?php else: foreach ($posts as $post):
    $liked = userLikedPost($sql, $post['id'], $currentUserId);
?>
    <div class="post-card" id="post-<?= $post['id'] ?>">
        <div class="post-header">
            <!-- Avatar autore post: usa avatarSrc() -->
            <div class="post-avatar">
                <img src="<?= htmlspecialchars(avatarSrc($post['avatar_url'])) ?>"
                     onerror="this.src='../img/default_avatar.png'"
                     alt="@<?= htmlspecialchars($post['username']) ?>">
            </div>
            <div>
                <a href="profile.php?user=<?= urlencode($post['username']) ?>" class="post-username">
                    @<?= htmlspecialchars($post['username']) ?>
                </a>
                <div class="post-meta"><?= htmlspecialchars($post['cuisine_type'] ?? '') ?></div>
            </div>
            <span class="post-time"><?= timeAgo($post['created_at']) ?></span>
        </div>

        <?php $imgSrc = postImageSrc($post['image_path']); ?>
        <?php if ($imgSrc): ?>
            <img class="post-image"
                 src="<?= htmlspecialchars($imgSrc) ?>"
                 alt="<?= htmlspecialchars($post['title_work']) ?>">
        <?php else: ?>
            <div class="post-image-placeholder">🍽️</div>
        <?php endif; ?>

        <div class="post-actions">
            <?php if (!$isGuest): ?>
            <button class="action-btn <?= $liked ? 'liked' : '' ?>"
                    onclick="toggleLike(<?= $post['id'] ?>, this)">
                <span class="icon"><?= $liked ? '❤️' : '🤍' ?></span>
            </button>
            <?php else: ?>
            <span class="action-btn"><span class="icon">🤍</span></span>
            <?php endif; ?>

            <a href="post.php?id=<?= $post['id'] ?>" class="action-btn">
                <span class="icon">💬</span>
                <span><?= $post['comments_count'] ?></span>
            </a>

            <div class="stars-row">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span style="font-size:13px;color:<?= $i <= $post['rating'] ? 'var(--or)' : '#ddd' ?>">★</span>
                <?php endfor; ?>
            </div>
        </div>

        <div class="post-likes" id="likes-count-<?= $post['id'] ?>">
            <?= $post['likes_count'] ?> mi piace
        </div>
        <div class="post-caption">
            <span class="author">@<?= htmlspecialchars($post['username']) ?></span>
            <?= nl2br(htmlspecialchars($post['content'])) ?>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>

<nav class="bottom-nav">
    <a href="feed.php" class="bn-item active">
        <span class="bn-icon">🏠</span><span class="bn-label">Home</span>
    </a>
    <a href="explore.php" class="bn-item">
        <span class="bn-icon">🔍</span><span class="bn-label">Esplora</span>
    </a>
    <?php if (!$isGuest): ?>
    <a href="new_post.php" class="bn-item">
        <span class="bn-icon" style="font-size:30px;color:var(--tc)">＋</span>
        <span class="bn-label">Pubblica</span>
    </a>
    <?php else: ?>
    <a href="../backend/login/registration.php" class="bn-item">
        <span class="bn-icon" style="font-size:30px;color:#ccc">＋</span>
        <span class="bn-label">Pubblica</span>
    </a>
    <?php endif; ?>
    <a href="notifications.php" class="bn-item">
        <span class="bn-icon">🔔</span><span class="bn-label">Notifiche</span>
    </a>
    <a href="profile.php?user=<?= urlencode($currentUsername) ?>" class="bn-item">
        <span class="bn-icon">👤</span><span class="bn-label">Profilo</span>
    </a>
</nav>

<script>
function toggleLike(postId, btn) {
    fetch('/tastegram/backend/api/like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('.icon');
            icon.textContent = data.liked ? '❤️' : '🤍';
            btn.classList.toggle('liked', data.liked);
            document.getElementById('likes-count-' + postId).textContent = data.likes_count + ' mi piace';
        }
    })
    .catch(() => alert('Errore di rete sul like, riprova.'));
}
</script>

</body>
</html>
