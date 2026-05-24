<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$itemId = (int) ($_GET['id'] ?? 0);
if ($itemId <= 0) { header('Location: explore.php?tab=shop'); exit; }

$stmt = $sql->prepare("
    SELECT s.*, u.username, u.avatar_url, u.followers_count
    FROM shop_items s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    die('<p style="text-align:center;margin-top:60px;font-family:sans-serif">Articolo non trovato.</p>');
}

$isOwner = ($item['user_id'] === $currentUserId);

// ── Elimina articolo (autore o admin) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    if ($isOwner || $isAdmin) {
        if (!empty($item['image_path'])) {
            $fp = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/tastegram/img/uploads/shops/' . basename($item['image_path']);
            if (file_exists($fp)) unlink($fp);
        }
        $sql->prepare("DELETE FROM shop_items WHERE id = ?")->execute([$itemId]);
        header('Location: explore.php?tab=shop');
        exit;
    }
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'ora';
    if ($diff < 3600)   return floor($diff/60) . 'm fa';
    if ($diff < 86400)  return floor($diff/3600) . 'h fa';
    if ($diff < 604800) return floor($diff/86400) . 'g fa';
    return date('d/m/Y', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> — Tastegram Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 80px; }

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
        .nav-title {
            font-size: 15px; font-weight: 700; color: var(--br); flex: 1;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .nav-delete {
            background: none; border: none; cursor: pointer; font-size: 20px;
            color: #d85140; padding: 4px;
        }

        .item-wrap { max-width: 480px; margin: 0 auto; }

        /* Immagine */
        .item-image {
            width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; background: var(--cr);
        }
        .item-image-placeholder {
            width: 100%; aspect-ratio: 1/1; background: linear-gradient(135deg,#f5e6d3,#fce8d0);
            display: flex; align-items: center; justify-content: center; font-size: 80px;
        }

        /* Prezzo + titolo */
        .item-main {
            background: #fff; padding: 16px; border-bottom: 1px solid #f0f0f0;
        }
        .item-price {
            font-size: 28px; font-weight: 700; color: var(--tc); margin-bottom: 6px;
        }
        .item-title { font-size: 18px; font-weight: 700; color: var(--br); margin-bottom: 4px; }
        .item-time  { font-size: 12px; color: #bbb; margin-bottom: 14px; }

        .btn-contact {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 14px; background: var(--tc); color: #fff;
            border: none; border-radius: 14px; font-size: 16px; font-weight: 700;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            text-decoration: none; transition: opacity .2s;
        }
        .btn-contact:hover { opacity: .88; }
        .btn-contact.guest {
            background: #eee; color: #aaa; cursor: default; pointer-events: none;
        }

        /* Descrizione */
        .item-description {
            background: #fff; padding: 16px; margin-top: 8px; border-bottom: 1px solid #f0f0f0;
        }
        .desc-label {
            font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #bbb; margin-bottom: 10px;
        }
        .desc-text {
            font-size: 14px; color: #444; line-height: 1.7; white-space: pre-line;
        }

        /* Venditore */
        .seller-card {
            background: #fff; padding: 14px 16px; margin-top: 8px;
            display: flex; align-items: center; gap: 12px;
            text-decoration: none; color: inherit;
            border-bottom: 1px solid #f0f0f0;
        }
        .seller-card:hover { background: var(--cr); }
        .seller-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or); flex-shrink: 0;
        }
        .seller-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .seller-info { flex: 1; }
        .seller-label { font-size: 11px; color: #bbb; font-weight: 600; text-transform: uppercase; }
        .seller-name { font-size: 14px; font-weight: 700; color: var(--br); margin-top: 2px; }
        .seller-followers { font-size: 12px; color: #999; margin-top: 2px; }
        .seller-arrow { color: #ccc; font-size: 20px; }

        /* Bottom nav */
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
    <a href="explore.php?tab=shop" class="nav-back">←</a>
    <span class="nav-title"><?= htmlspecialchars($item['title']) ?></span>
    <?php if ($isOwner || $isAdmin): ?>
        <form method="POST" style="margin:0" onsubmit="return confirm('Eliminare questo articolo?')">
            <input type="hidden" name="delete_item" value="1">
            <button type="submit" class="nav-delete" title="Elimina articolo">🗑</button>
        </form>
    <?php endif; ?>
</nav>

<div class="item-wrap">

    <!-- Immagine -->
    <?php if (!empty($item['image_path'])): ?>
        <img class="item-image"
             src="../img/uploads/shops/<?= htmlspecialchars($item['image_path']) ?>"
             onerror="this.style.display='none'"
             alt="<?= htmlspecialchars($item['title']) ?>">
    <?php else: ?>
        <div class="item-image-placeholder">🛒</div>
    <?php endif; ?>

    <!-- Prezzo, titolo e CTA -->
    <div class="item-main">
        <div class="item-price">€<?= number_format((float)$item['price'], 2, ',', '.') ?></div>
        <div class="item-title"><?= htmlspecialchars($item['title']) ?></div>
        <div class="item-time">Pubblicato <?= timeAgo($item['created_at']) ?></div>

        <?php if (!$isGuest && !$isOwner): ?>
            <a href="contact_seller.php?item=<?= $itemId ?>" class="btn-contact">
                ✉️ Contatta il venditore
            </a>
        <?php elseif ($isOwner): ?>
            <div style="text-align:center;font-size:13px;color:#999;padding:10px 0">
                Questo è il tuo annuncio
            </div>
        <?php else: ?>
            <a href="../backend/login/registration.php" class="btn-contact guest">
                Registrati per contattare
            </a>
        <?php endif; ?>
    </div>

    <!-- Descrizione -->
    <div class="item-description">
        <div class="desc-label">📋 Descrizione</div>
        <div class="desc-text"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
    </div>

    <!-- Card venditore -->
    <a href="profile.php?user=<?= urlencode($item['username']) ?>" class="seller-card">
        <div class="seller-avatar">
            <img src="<?= htmlspecialchars(avatarSrc($item['avatar_url'])) ?>"
                 onerror="this.src='../img/default_avatar.png'"
                 alt="@<?= htmlspecialchars($item['username']) ?>">
        </div>
        <div class="seller-info">
            <div class="seller-label">Venduto da</div>
            <div class="seller-name">@<?= htmlspecialchars($item['username']) ?></div>
            <div class="seller-followers"><?= number_format($item['followers_count']) ?> follower</div>
        </div>
        <span class="seller-arrow">›</span>
    </a>

</div>

<!-- Bottom nav -->
<nav class="bottom-nav">
    <a href="feed.php" class="bn-item"><span class="bn-icon">🏠</span><span class="bn-label">Home</span></a>
    <a href="explore.php" class="bn-item active"><span class="bn-icon">🔍</span><span class="bn-label">Esplora</span></a>
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
    <a href="notifications.php" class="bn-item"><span class="bn-icon">🔔</span><span class="bn-label">Notifiche</span></a>
    <a href="profile.php?user=<?= urlencode($currentUsername) ?>" class="bn-item">
        <span class="bn-icon">👤</span><span class="bn-label">Profilo</span>
    </a>
</nav>

</body>
</html>
