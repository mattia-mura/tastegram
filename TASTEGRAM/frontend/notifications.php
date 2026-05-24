<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

// Segna tutte le notifiche come lette
$sql->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
    ->execute([$currentUserId]);

// Carica notifiche con dati dell'attore
$stmt = $sql->prepare("
    SELECT n.*, 
           u.username  AS actor_username,
           u.avatar_url AS actor_avatar,
           p.title_work AS post_title,
           p.image_path AS post_image
    FROM notifications n
    JOIN users u ON u.id = n.actor_id
    LEFT JOIN posts p ON p.id = n.post_id
    WHERE n.user_id = :uid
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->execute([':uid' => $currentUserId]);
$notifications = $stmt->fetchAll();

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'ora';
    if ($diff < 3600)   return floor($diff/60) . 'm fa';
    if ($diff < 86400)  return floor($diff/3600) . 'h fa';
    if ($diff < 604800) return floor($diff/86400) . 'g fa';
    return date('d/m/Y', strtotime($dt));
}

$typeLabel = [
    'like'    => 'ha messo like al tuo post',
    'comment' => 'ha commentato il tuo post',
    'follow'  => 'ha iniziato a seguirti',
];
$typeIcon = [
    'like'    => '❤️',
    'comment' => '💬',
    'follow'  => '👤',
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifiche — Tastegram</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --am:#F0882A; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 70px; }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky; top: 0; z-index: 100;
            background: #fff; border-bottom: 1px solid #eee;
            display: flex; align-items: center;
            padding: 0 16px; height: 54px; gap: 12px;
        }
        .nav-back {
            width: 34px; height: 34px; border-radius: 50%;
            background: var(--cr); border: none; cursor: pointer;
            font-size: 18px; display: flex; align-items: center;
            justify-content: center; text-decoration: none; color: var(--tc);
        }
        .nav-title { font-size: 16px; font-weight: 700; color: var(--br); flex: 1; }

        /* ── LISTA ── */
        .notif-wrap { max-width: 480px; margin: 0 auto; }

        .notif-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px; background: #fff;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none; color: inherit;
            transition: background .15s;
        }
        .notif-item:hover { background: var(--cr); }
        .notif-item.unread { background: #fff8f5; }
        .notif-item.unread:hover { background: #fdf0e8; }

        .notif-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or); flex-shrink: 0;
            position: relative;
        }
        .notif-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .notif-type-badge {
            position: absolute; bottom: -2px; right: -2px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #fff; display: flex; align-items: center;
            justify-content: center; font-size: 11px;
            border: 1.5px solid #fff;
        }

        .notif-body { flex: 1; min-width: 0; }
        .notif-text { font-size: 13px; color: #333; line-height: 1.4; }
        .notif-text b { color: var(--br); }
        .notif-time { font-size: 11px; color: #bbb; margin-top: 3px; }

        .notif-thumb {
            width: 46px; height: 46px; border-radius: 8px;
            overflow: hidden; flex-shrink: 0; background: var(--cr);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        .notif-thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* ── GRUPPI PER DATA ── */
        .date-group {
            padding: 10px 16px 6px;
            font-size: 11px; font-weight: 700; color: #bbb;
            text-transform: uppercase; letter-spacing: .5px;
            background: #fafafa;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 70px 24px; color: #bbb;
        }
        .empty-state .emoji { font-size: 50px; margin-bottom: 14px; }
        .empty-state p { font-size: 15px; }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 58px; background: #fff;
            border-top: 1px solid #eee;
            display: flex; z-index: 100;
        }
        .bn-item {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 2px;
            text-decoration: none; color: #aaa;
            border: none; background: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
        }
        .bn-item .bn-icon { font-size: 22px; }
        .bn-item .bn-label { font-size: 10px; }
        .bn-item.active { color: var(--tc); }
        .bn-item.active .bn-label { font-weight: 700; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="feed.php" class="nav-back">←</a>
    <span class="nav-title">Notifiche</span>
</nav>

<div class="notif-wrap">
<?php if (empty($notifications)): ?>
    <div class="empty-state">
        <div class="emoji">🔔</div>
        <p>Nessuna notifica ancora.<br>Inizia a seguire altri chef!</p>
    </div>

<?php else:
    $prevGroup = '';
    foreach ($notifications as $n):
        // Raggruppa per oggi / ieri / prima
        $ts   = strtotime($n['created_at']);
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $nDate     = date('Y-m-d', $ts);

        if ($nDate === $today)          $group = 'Oggi';
        elseif ($nDate === $yesterday)  $group = 'Ieri';
        else                            $group = date('d/m/Y', $ts);

        if ($group !== $prevGroup):
            $prevGroup = $group;
?>
        <div class="date-group"><?= $group ?></div>
<?php   endif;

        // Costruisci URL destinazione
        $href = $n['post_id']
            ? 'post.php?id=' . $n['post_id']
            : 'profile.php?user=' . urlencode($n['actor_username']);
?>
        <a href="<?= $href ?>" class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">

            <!-- Avatar attore -->
            <div class="notif-avatar">
                <img src="../img/<?= htmlspecialchars($n['actor_avatar'] ?: 'default_avatar.png') ?>"
                     onerror="this.src='../img/default_avatar.png'"
                     alt="@<?= htmlspecialchars($n['actor_username']) ?>">
                <div class="notif-type-badge"><?= $typeIcon[$n['type']] ?? '🔔' ?></div>
            </div>

            <!-- Testo -->
            <div class="notif-body">
                <div class="notif-text">
                    <b>@<?= htmlspecialchars($n['actor_username']) ?></b>
                    <?= $typeLabel[$n['type']] ?? '' ?>
                    <?php if (!empty($n['post_title'])): ?>
                        — <em><?= htmlspecialchars(mb_strimwidth($n['post_title'], 0, 30, '…')) ?></em>
                    <?php endif; ?>
                </div>
                <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
            </div>

            <!-- Thumbnail post (solo like e commenti) -->
            <?php if ($n['post_id']): ?>
            <div class="notif-thumb">
                <?php if (!empty($n['post_image'])): ?>
                    <img src="../img/uploads/foto/<?= htmlspecialchars($n['post_image']) ?>"
                         onerror="this.style.display='none'"
                         alt="">
                <?php else: ?>
                    🍽️
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </a>
<?php endforeach; endif; ?>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="feed.php" class="bn-item">
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
    <?php endif; ?>
    <a href="notifications.php" class="bn-item active">
        <span class="bn-icon">🔔</span><span class="bn-label">Notifiche</span>
    </a>
    <a href="profile.php?user=<?= urlencode($currentUsername) ?>" class="bn-item">
        <span class="bn-icon">👤</span><span class="bn-label">Profilo</span>
    </a>
</nav>

</body>
</html>
