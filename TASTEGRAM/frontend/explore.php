<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$query    = trim($_GET['q'] ?? '');
$tab      = $_GET['tab'] ?? 'posts'; // posts | users | news | shop
$results  = [];
$hasQuery = $query !== '';

if ($hasQuery) {
    if ($tab === 'users') {
        $stmt = $sql->prepare("
            SELECT id, username, avatar_url, bio, followers_count
            FROM users
            WHERE (username LIKE :q1 OR bio LIKE :q2)
              AND username != 'ospite'
            ORDER BY followers_count DESC
            LIMIT 30
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm]);
        $results = $stmt->fetchAll();

        foreach ($results as &$u) {
            $fs = $sql->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
            $fs->execute([$currentUserId, $u['id']]);
            $u['is_following'] = (bool) $fs->fetchColumn();
            $u['is_self']      = ($u['id'] === $currentUserId);
        }
        unset($u);

    } elseif ($tab === 'shop') {
        $stmt = $sql->prepare("
            SELECT s.*, u.username, u.avatar_url
            FROM shop_items s
            JOIN users u ON u.id = s.user_id
            WHERE s.title LIKE :q1 OR s.description LIKE :q2
            ORDER BY s.created_at DESC
            LIMIT 30
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm]);
        $results = $stmt->fetchAll();

    } else {
        // posts
        $stmt = $sql->prepare("
            SELECT p.id, p.title_work, p.image_path, p.likes_count,
                   p.comments_count, p.rating, p.cuisine_type, p.created_at,
                   u.username, u.avatar_url
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.title_work LIKE :q1
               OR p.cuisine_type LIKE :q2
               OR p.content LIKE :q3
            ORDER BY p.likes_count DESC, p.created_at DESC
            LIMIT 30
        ");
        $searchTerm = '%' . $query . '%';
        $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm]);
        $results = $stmt->fetchAll();
    }

} else {
    // Senza query: trending / suggeriti / ultimi
    if ($tab === 'users') {
        $stmt = $sql->prepare("
            SELECT id, username, avatar_url, bio, followers_count
            FROM users
            WHERE username != 'ospite' AND id != :uid
            ORDER BY followers_count DESC
            LIMIT 20
        ");
        $stmt->execute([':uid' => $currentUserId]);
        $results = $stmt->fetchAll();

        foreach ($results as &$u) {
            $fs = $sql->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
            $fs->execute([$currentUserId, $u['id']]);
            $u['is_following'] = (bool) $fs->fetchColumn();
            $u['is_self']      = false;
        }
        unset($u);

    } elseif ($tab === 'shop') {
        $stmt = $sql->prepare("
            SELECT s.*, u.username, u.avatar_url
            FROM shop_items s
            JOIN users u ON u.id = s.user_id
            ORDER BY s.created_at DESC
            LIMIT 30
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();

    } else {
        // posts trending
        $stmt = $sql->prepare("
            SELECT p.id, p.title_work, p.image_path, p.likes_count,
                   p.comments_count, p.rating, p.cuisine_type, p.created_at,
                   u.username, u.avatar_url
            FROM posts p
            JOIN users u ON u.id = p.user_id
            ORDER BY p.likes_count DESC, p.created_at DESC
            LIMIT 30
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
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
    <title>Esplora — Tastegram</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --am:#F0882A; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 70px; }

        /* ── NAVBAR ── */
        .navbar {
            position: sticky; top: 0; z-index: 100;
            background: #fff; border-bottom: 1px solid #eee;
            padding: 10px 16px; display: flex; flex-direction: column; gap: 10px;
        }
        .navbar-top { display: flex; align-items: center; gap: 10px; }
        .nav-logo { font-size: 20px; font-weight: 700; color: var(--tc); text-decoration: none; }

        /* Search bar */
        .search-form { display: flex; align-items: center; flex: 1; gap: 8px; }
        .search-input-wrap { flex: 1; position: relative; }
        .search-input {
            width: 100%; padding: 10px 16px 10px 38px;
            border: 2px solid #f0f0f0; border-radius: 22px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s; background: #f5f5f5;
        }
        .search-input:focus { outline: none; border-color: var(--or); background: var(--cr); }
        .search-icon {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); font-size: 15px; pointer-events: none;
        }
        .search-clear {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%); font-size: 15px;
            background: none; border: none; cursor: pointer;
            color: #bbb; display: none;
        }

        /* Tab */
        .tabs { display: flex; gap: 0; border-bottom: 1px solid #f0f0f0; overflow-x: auto; }
        .tab-btn {
            flex: 1; padding: 8px 4px; text-align: center;
            font-size: 12px; font-weight: 600; color: #aaa;
            text-decoration: none; border-bottom: 2px solid transparent;
            transition: all .2s; white-space: nowrap; min-width: 0;
        }
        .tab-btn.active { color: var(--tc); border-bottom-color: var(--tc); }

        /* ── GRIGLIA POST ── */
        .content-wrap { max-width: 480px; margin: 0 auto; }
        .post-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 2px; padding: 2px;
        }
        .grid-item {
            aspect-ratio: 1/1; overflow: hidden; position: relative;
            cursor: pointer; background: var(--cr);
            display: flex; align-items: center; justify-content: center;
        }
        .grid-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .grid-placeholder { font-size: 34px; }
        .grid-overlay {
            position: absolute; inset: 0;
            background: rgba(0,0,0,0); color: #fff;
            display: flex; align-items: center; justify-content: center;
            gap: 12px; font-size: 12px; font-weight: 700;
            transition: background .2s;
        }
        .grid-item:hover .grid-overlay { background: rgba(0,0,0,0.40); }
        .grid-badge {
            position: absolute; bottom: 5px; left: 5px;
            background: rgba(0,0,0,0.55); color: #fff;
            font-size: 10px; font-weight: 600; padding: 2px 7px;
            border-radius: 10px; pointer-events: none;
        }

        /* ── LISTA UTENTI ── */
        .user-list { padding: 8px 0; }
        .user-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; border-bottom: 1px solid #f5f5f5;
            background: #fff;
        }
        .user-avatar {
            width: 50px; height: 50px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or);
            flex-shrink: 0; cursor: pointer; text-decoration: none;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { flex: 1; min-width: 0; }
        .user-username {
            font-size: 14px; font-weight: 700; color: var(--br);
            text-decoration: none; display: block;
        }
        .user-username:hover { text-decoration: underline; }
        .user-bio {
            font-size: 12px; color: #888; margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .user-followers { font-size: 11px; color: #bbb; margin-top: 2px; }

        .btn-follow-sm {
            padding: 6px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            border: 1.5px solid; font-family: 'DM Sans', sans-serif;
            transition: all .2s; flex-shrink: 0;
        }
        .btn-follow-sm.follow    { background: var(--tc); border-color: var(--tc); color: #fff; }
        .btn-follow-sm.following { background: #f0f0f0; border-color: #ddd; color: #555; }
        .btn-follow-sm.self      { display: none; }

        /* ── NEWS ── */
        .news-list { padding: 4px 0; }
        .news-card {
            display: flex; gap: 12px; padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0; background: #fff;
            text-decoration: none; color: inherit; transition: background .15s;
        }
        .news-card:hover { background: var(--cr); }
        .news-thumb {
            width: 80px; height: 80px; border-radius: 12px; flex-shrink: 0;
            overflow: hidden; background: #f0e8de;
            display: flex; align-items: center; justify-content: center; font-size: 28px;
        }
        .news-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .news-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
        .news-source { font-size: 10px; font-weight: 700; color: var(--tc); text-transform: uppercase; }
        .news-title {
            font-size: 14px; font-weight: 700; color: var(--br); line-height: 1.35;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .news-desc {
            font-size: 12px; color: #888; line-height: 1.4;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .news-time { font-size: 11px; color: #bbb; margin-top: auto; }
        .news-loading { text-align: center; padding: 48px 24px; color: #bbb; }
        .news-loading .spinner {
            width: 32px; height: 32px; border: 3px solid #f0e0d0;
            border-top-color: var(--or); border-radius: 50%;
            animation: spin .8s linear infinite; margin: 0 auto 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .news-error { text-align: center; padding: 48px 24px; color: #bbb; }
        .news-error .emoji { font-size: 44px; margin-bottom: 10px; }
        .news-lang { display: flex; gap: 8px; padding: 10px 16px 0; }
        .lang-btn {
            padding: 4px 12px; border-radius: 14px; font-size: 12px; font-weight: 600;
            cursor: pointer; border: 1.5px solid #ddd; background: transparent;
            font-family: 'DM Sans', sans-serif; transition: all .2s;
        }
        .lang-btn.active { background: var(--tc); border-color: var(--tc); color: #fff; }

        /* ── SHOP ── */
        .shop-header-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px 6px;
        }
        .shop-header-bar .section-label { padding: 0; }
        .btn-sell {
            padding: 7px 16px; background: var(--tc); color: #fff; border: none;
            border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif; text-decoration: none;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-sell:hover { opacity: .88; }

        .shop-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px; padding: 10px 16px;
        }
        .shop-card {
            background: #fff; border-radius: 14px;
            overflow: hidden; cursor: pointer;
            border: 1px solid #f0f0f0;
            text-decoration: none; color: inherit;
            transition: transform .15s, box-shadow .15s;
            display: flex; flex-direction: column;
        }
        .shop-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .shop-card-img {
            width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block;
            background: var(--cr);
        }
        .shop-card-placeholder {
            width: 100%; aspect-ratio: 1/1; background: linear-gradient(135deg,#f5e6d3,#fce8d0);
            display: flex; align-items: center; justify-content: center; font-size: 44px;
        }
        .shop-card-body { padding: 10px 12px 12px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .shop-card-price { font-size: 16px; font-weight: 700; color: var(--tc); }
        .shop-card-title {
            font-size: 13px; font-weight: 600; color: var(--br);
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .shop-card-seller {
            font-size: 11px; color: #999; margin-top: auto; padding-top: 4px;
            display: flex; align-items: center; gap: 4px;
        }
        .shop-card-seller img {
            width: 16px; height: 16px; border-radius: 50%; object-fit: cover;
        }

        /* ── SEZIONE LABEL ── */
        .section-label {
            padding: 12px 16px 6px;
            font-size: 12px; font-weight: 700; color: #bbb;
            text-transform: uppercase; letter-spacing: .5px;
        }

        /* ── EMPTY STATE ── */
        .empty-state { text-align: center; padding: 60px 24px; color: #bbb; }
        .empty-state .emoji { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; line-height: 1.6; }
        .empty-state a {
            display: inline-block; margin-top: 16px; padding: 10px 24px;
            background: var(--tc); color: #fff; border-radius: 12px;
            text-decoration: none; font-weight: 600; font-size: 14px;
        }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: 58px; background: #fff;
            border-top: 1px solid #eee; display: flex; z-index: 100;
        }
        .bn-item {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 2px;
            text-decoration: none; color: #aaa;
            border: none; background: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
        }
        .bn-item .bn-icon  { font-size: 22px; }
        .bn-item .bn-label { font-size: 10px; }
        .bn-item.active    { color: var(--tc); }
        .bn-item.active .bn-label { font-weight: 700; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-top">
        <a href="feed.php" class="nav-logo">tastegram</a>
        <form class="search-form" method="GET" action="explore.php" id="search-form">
            <div class="search-input-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="q" id="search-input" class="search-input"
                       placeholder="Cerca piatti, utenti, articoli..."
                       value="<?= htmlspecialchars($query) ?>"
                       autocomplete="off">
                <button type="button" class="search-clear" id="clear-btn" onclick="clearSearch()">✕</button>
            </div>
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        </form>
    </div>

    <!-- Tab -->
    <div class="tabs">
        <a href="explore.php?tab=posts<?= $hasQuery ? '&q='.urlencode($query) : '' ?>"
           class="tab-btn <?= $tab === 'posts' ? 'active' : '' ?>">🍽️ Piatti</a>
        <a href="explore.php?tab=users<?= $hasQuery ? '&q='.urlencode($query) : '' ?>"
           class="tab-btn <?= $tab === 'users' ? 'active' : '' ?>">👤 Utenti</a>
        <a href="explore.php?tab=news"
           class="tab-btn <?= $tab === 'news' ? 'active' : '' ?>">📰 Novità</a>
        <a href="explore.php?tab=shop<?= $hasQuery ? '&q='.urlencode($query) : '' ?>"
           class="tab-btn <?= $tab === 'shop' ? 'active' : '' ?>">🛒 Shop</a>
    </div>
</nav>

<div class="content-wrap">

    <?php if ($tab === 'posts'): ?>
        <div class="section-label">
            <?= $hasQuery
                ? '🔍 Risultati per "' . htmlspecialchars($query) . '"'
                : '🔥 Post in evidenza' ?>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state">
                <div class="emoji">🍳</div>
                <p>Nessun piatto trovato per<br><strong>"<?= htmlspecialchars($query) ?>"</strong></p>
            </div>
        <?php else: ?>
            <div class="post-grid">
                <?php foreach ($results as $p): ?>
                    <div class="grid-item" onclick="window.location='post.php?id=<?= $p['id'] ?>'">
                        <?php if (!empty($p['image_path'])): ?>
                            <img src="../img/uploads/foto/<?= htmlspecialchars($p['image_path']) ?>"
                                 onerror="this.style.display='none'"
                                 alt="<?= htmlspecialchars($p['title_work']) ?>">
                        <?php else: ?>
                            <div class="grid-placeholder">🍽️</div>
                        <?php endif; ?>
                        <div class="grid-overlay">
                            <span>❤️ <?= $p['likes_count'] ?></span>
                            <span>💬 <?= $p['comments_count'] ?></span>
                        </div>
                        <?php if (!empty($p['cuisine_type'])): ?>
                            <div class="grid-badge"><?= htmlspecialchars($p['cuisine_type']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'users'): ?>
        <div class="section-label">
            <?= $hasQuery
                ? '🔍 Utenti trovati per "' . htmlspecialchars($query) . '"'
                : '👥 Utenti da scoprire' ?>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state">
                <div class="emoji">👤</div>
                <p>Nessun utente trovato per<br><strong>"<?= htmlspecialchars($query) ?>"</strong></p>
            </div>
        <?php else: ?>
            <div class="user-list">
                <?php foreach ($results as $u): ?>
                    <div class="user-item">
                        <a href="profile.php?user=<?= urlencode($u['username']) ?>" class="user-avatar">
                            <img src="<?= htmlspecialchars(avatarSrc($u['avatar_url'] ?? 'default_avatar.png')) ?>"
                                 onerror="this.src='../img/default_avatar.png'"
                                 alt="@<?= htmlspecialchars($u['username']) ?>">
                        </a>
                        <div class="user-info">
                            <a href="profile.php?user=<?= urlencode($u['username']) ?>" class="user-username">
                                @<?= htmlspecialchars($u['username']) ?>
                            </a>
                            <?php if (!empty($u['bio'])): ?>
                                <div class="user-bio"><?= htmlspecialchars(mb_strimwidth($u['bio'], 0, 60, '…')) ?></div>
                            <?php endif; ?>
                            <div class="user-followers"><?= number_format($u['followers_count'] ?? 0) ?> follower</div>
                        </div>
                        <?php if (!$isGuest && !($u['is_self'] ?? false)): ?>
                            <button class="btn-follow-sm <?= ($u['is_following'] ?? false) ? 'following' : 'follow' ?>"
                                    id="follow-<?= $u['id'] ?>"
                                    onclick="toggleFollow(<?= $u['id'] ?>)">
                                <?= ($u['is_following'] ?? false) ? '✓ Seguito' : '+ Segui' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'news'): ?>
        <div class="news-lang">
            <button class="lang-btn active" id="lang-it" onclick="setLang('it')">🇮🇹 Italiano</button>
            <button class="lang-btn" id="lang-en" onclick="setLang('en')">🇬🇧 English</button>
        </div>
        <div class="section-label" style="margin-top:4px">📰 Ultime notizie food</div>
        <div id="news-container" class="news-list">
            <div class="news-loading"><div class="spinner"></div><div>Caricamento notizie...</div></div>
        </div>
        <div id="news-load-more" style="display:none;text-align:center;padding:16px">
            <button onclick="loadNews(true)" style="padding:10px 24px;background:transparent;
                border:1.5px solid var(--tc);border-radius:12px;color:var(--tc);
                font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px">
                Carica altre
            </button>
        </div>

    <?php elseif ($tab === 'shop'): ?>
        <!-- Header Shop con pulsante "Metti in vendita" -->
        <div class="shop-header-bar">
            <div class="section-label" style="padding:0">
                <?= $hasQuery
                    ? '🔍 Risultati per "' . htmlspecialchars($query) . '"'
                    : '🛒 Articoli in vendita' ?>
            </div>
            <?php if (!$isGuest): ?>
                <a href="new_shop_item.php" class="btn-sell">＋ Vendi</a>
            <?php else: ?>
                <a href="../backend/login/registration.php" class="btn-sell" style="background:#ccc">＋ Vendi</a>
            <?php endif; ?>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state">
                <div class="emoji">🛒</div>
                <?php if ($hasQuery): ?>
                    <p>Nessun articolo trovato per<br><strong>"<?= htmlspecialchars($query) ?>"</strong></p>
                <?php else: ?>
                    <p>Ancora nessun articolo in vendita.<br>Sii il primo a pubblicare!</p>
                    <?php if (!$isGuest): ?>
                        <a href="new_shop_item.php">＋ Metti in vendita</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="shop-grid">
                <?php foreach ($results as $item): ?>
                    <a href="shop_item.php?id=<?= $item['id'] ?>" class="shop-card">
                        <?php if (!empty($item['image_path'])): ?>
                            <img class="shop-card-img"
                                 src="../img/uploads/shops/<?= htmlspecialchars($item['image_path']) ?>"
                                 onerror="this.style.display='none'"
                                 alt="<?= htmlspecialchars($item['title']) ?>">
                        <?php else: ?>
                            <div class="shop-card-placeholder">🛒</div>
                        <?php endif; ?>
                        <div class="shop-card-body">
                            <div class="shop-card-price">€<?= number_format((float)$item['price'], 2, ',', '.') ?></div>
                            <div class="shop-card-title"><?= htmlspecialchars($item['title']) ?></div>
                            <div class="shop-card-seller">
                                <img src="<?= htmlspecialchars(avatarSrc($item['avatar_url'])) ?>"
                                     onerror="this.src='../img/default_avatar.png'"
                                     alt="">
                                @<?= htmlspecialchars($item['username']) ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="feed.php" class="bn-item">
        <span class="bn-icon">🏠</span><span class="bn-label">Home</span>
    </a>
    <a href="explore.php" class="bn-item active">
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
// Mostra/nascondi pulsante clear
const searchInput = document.getElementById('search-input');
const clearBtn    = document.getElementById('clear-btn');

function updateClearBtn() {
    clearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
}
searchInput.addEventListener('input', updateClearBtn);
updateClearBtn();

// Submit automatico dopo 500ms di pausa
let searchTimer;
searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    if (this.value.length === 0) {
        document.getElementById('search-form').submit();
        return;
    }
    if (this.value.length >= 2) {
        searchTimer = setTimeout(() => {
            document.getElementById('search-form').submit();
        }, 500);
    }
});

function clearSearch() {
    searchInput.value = '';
    clearBtn.style.display = 'none';
    document.getElementById('search-form').submit();
}

// Follow/unfollow inline
function toggleFollow(userId) {
    fetch('../backend/api/follow.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_id: userId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('follow-' + userId);
            btn.textContent = data.following ? '✓ Seguito' : '+ Segui';
            btn.className   = 'btn-follow-sm ' + (data.following ? 'following' : 'follow');
        }
    });
}
</script>

<?php if ($tab === 'news'): ?>
<script>
let newsPage = 1, newsLang = 'it', newsLoading = false;

function formatNewsDate(iso) {
    const diff = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (diff < 3600)   return Math.floor(diff / 60) + ' min fa';
    if (diff < 86400)  return Math.floor(diff / 3600) + 'h fa';
    if (diff < 604800) return Math.floor(diff / 86400) + 'g fa';
    return new Date(iso).toLocaleDateString('it-IT', {day:'2-digit', month:'short'});
}

function renderNews(articles, append) {
    const c = document.getElementById('news-container');
    if (!append) c.innerHTML = '';
    if (!articles.length && !append) {
        c.innerHTML = `<div class="news-error"><div class="emoji">📰</div>
            <p>Nessuna notizia disponibile.<br>Inserisci la tua API key in gnews.php</p></div>`;
        return;
    }
    articles.forEach(a => {
        const el = document.createElement('a');
        el.className = 'news-card';
        el.href = a.url; el.target = '_blank'; el.rel = 'noopener noreferrer';
        el.innerHTML = `
            <div class="news-thumb">
                ${a.image ? `<img src="${a.image}" onerror="this.parentNode.innerHTML='🍽️'" alt="">` : '🍽️'}
            </div>
            <div class="news-body">
                <div class="news-source">${a.source}</div>
                <div class="news-title">${a.title}</div>
                ${a.description ? `<div class="news-desc">${a.description}</div>` : ''}
                <div class="news-time">${formatNewsDate(a.publishedAt)}</div>
            </div>`;
        c.appendChild(el);
    });
}

function loadNews(append = false) {
    if (newsLoading) return;
    newsLoading = true;
    const c = document.getElementById('news-container');
    if (!append) c.innerHTML = '<div class="news-loading"><div class="spinner"></div><div>Caricamento...</div></div>';

    fetch(`../backend/api/gnews.php?action=food_news&lang=${newsLang}&page=${newsPage}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderNews(data.articles, append);
                newsPage++;
                document.getElementById('news-load-more').style.display =
                    data.articles.length === 10 ? 'block' : 'none';
            } else {
                c.innerHTML = `<div class="news-error"><div class="emoji">⚠️</div>
                    <p>${data.error ?? 'Errore notizie'}</p></div>`;
            }
        })
        .catch(() => {
            c.innerHTML = `<div class="news-error"><div class="emoji">📡</div><p>Errore di rete.</p></div>`;
        })
        .finally(() => { newsLoading = false; });
}

function setLang(lang) {
    newsLang = lang; newsPage = 1;
    document.getElementById('news-load-more').style.display = 'none';
    document.getElementById('lang-it').classList.toggle('active', lang === 'it');
    document.getElementById('lang-en').classList.toggle('active', lang === 'en');
    loadNews(false);
}

loadNews();
</script>
<?php endif; ?>

</body>
</html>
