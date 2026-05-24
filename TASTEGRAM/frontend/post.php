<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

$postId = (int) ($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: feed.php');
    exit;
}

// Carica il post con i dati dell'autore
$stmt = $sql->prepare("
    SELECT p.*, u.username, u.avatar_url
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    die('<p style="text-align:center;margin-top:60px;font-family:sans-serif">Post non trovato.</p>');
}

// L'utente loggato ha già messo like?
$likeStmt = $sql->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
$likeStmt->execute([$postId, $currentUserId]);
$liked = (bool) $likeStmt->fetchColumn();

// Carica commenti (solo root, depth=0) + risposte
$commStmt = $sql->prepare("
    SELECT c.*, u.username, u.avatar_url
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = :pid
    ORDER BY c.created_at ASC
");
$commStmt->execute([':pid' => $postId]);
$allComments = $commStmt->fetchAll();

// Organizza in albero: root → replies
$roots   = [];
$replies = [];
foreach ($allComments as $c) {
    if ($c['parent_id'] === null) {
        $roots[$c['id']] = $c;
    } else {
        $replies[$c['parent_id']][] = $c;
    }
}

// Gestione invio nuovo commento
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isGuest) {
    $commentText = trim($_POST['comment'] ?? '');
    $parentId    = (int) ($_POST['parent_id'] ?? 0) ?: null;
    $depth       = $parentId ? 1 : 0;

    if (empty($commentText)) {
        $commentError = 'Il commento non può essere vuoto.';
    } elseif (mb_strlen($commentText) > 500) {
        $commentError = 'Commento troppo lungo (max 500 caratteri).';
    // } else {
    //     $ins = $sql->prepare("
    //         INSERT INTO comments (post_id, user_id, parent_id, depth, content)
    //         VALUES (:pid, :uid, :parent, :depth, :content)
    //     ");
    //     $ins->execute([
    //         ':pid'     => $postId,
    //         ':uid'     => $currentUserId,
    //         ':parent'  => $parentId,
    //         ':depth'   => $depth,
    //         ':content' => $commentText,
    //     ]);
    } else {

        $ins = $sql->prepare("
            INSERT INTO comments (post_id, user_id, parent_id, content)
            VALUES (:pid, :uid, :parent, :content)
        ");

        // 3. Remove ':depth' from the execution array
        $ins->execute([
            ':pid'     => $postId,
            ':uid'     => $currentUserId,
            ':parent'  => $parentId,
            ':content' => $commentText,
        ]);

        // Notifica all'autore del post (se non è il proprio)
        if ($post['user_id'] !== $currentUserId) {
            $sql->prepare("
                INSERT INTO notifications (user_id, actor_id, post_id, type)
                VALUES (?, ?, ?, 'comment')
            ")->execute([$post['user_id'], $currentUserId, $postId]);
        }

        header('Location: post.php?id=' . $postId . '#comments');
        exit;
    }
}

$avatar = $post['avatar_url'] ?? 'default_avatar.png';

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
    <title><?= htmlspecialchars($post['title_work']) ?> — Tastegram</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --am:#F0882A; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 80px; }

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
        .nav-title { font-size: 15px; font-weight: 700; color: var(--br); flex: 1;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ── POST ── */
        .post-wrap { max-width: 480px; margin: 0 auto; }

        .post-header { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #fff; }
        .post-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or); flex-shrink: 0;
        }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .post-username { font-size: 14px; font-weight: 700; color: var(--br); text-decoration: none; }
        .post-username:hover { text-decoration: underline; }
        .post-date { font-size: 11px; color: #bbb; margin-top: 2px; }
        .post-cuisine-badge {
            margin-left: auto; padding: 4px 10px;
            background: var(--cr); border-radius: 20px;
            font-size: 11px; color: var(--tc); font-weight: 600;
        }

        .post-image {
            width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block;
            background: var(--cr);
        }
        .post-image-placeholder {
            width: 100%; aspect-ratio: 1/1; background: linear-gradient(135deg,#f5e6d3,#fce8d0);
            display: flex; align-items: center; justify-content: center; font-size: 80px;
        }

        .post-actions {
            background: #fff; display: flex; align-items: center;
            gap: 14px; padding: 10px 14px 6px;
        }
        .action-btn {
            background: none; border: none; cursor: pointer;
            display: flex; align-items: center; gap: 5px;
            font-size: 13px; color: #666; font-family: 'DM Sans', sans-serif; padding: 0;
        }
        .action-btn .icon { font-size: 24px; transition: transform .15s; }
        .action-btn:hover .icon { transform: scale(1.15); }
        .action-btn.liked .icon { color: var(--tc); }

        .stars-row { display: flex; gap: 2px; margin-left: auto; }
        .star { font-size: 15px; }

        .post-likes { padding: 2px 14px; background: #fff; font-size: 13px; font-weight: 700; color: var(--br); }

        /* Ricetta / contenuto */
        .post-content {
            background: #fff; padding: 12px 14px 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        .post-content .dish-title { font-size: 17px; font-weight: 700; color: var(--br); margin-bottom: 10px; }
        .post-content .recipe-text {
            font-size: 14px; color: #444; line-height: 1.7;
            white-space: pre-line;
        }

        /* ── COMMENTI ── */
        .comments-section { background: #fff; margin-top: 8px; }
        .comments-header {
            padding: 14px 16px 10px;
            font-size: 14px; font-weight: 700; color: var(--br);
            border-bottom: 1px solid #f0f0f0;
        }

        .comment {
            display: flex; gap: 10px; padding: 12px 16px;
            border-bottom: 1px solid #f8f8f8;
        }
        .comment.reply { padding-left: 48px; background: #fafafa; }
        .comment-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            overflow: hidden; border: 1.5px solid #eee; flex-shrink: 0;
        }
        .comment-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .comment-body { flex: 1; }
        .comment-user { font-size: 13px; font-weight: 700; color: var(--br); text-decoration: none; }
        .comment-user:hover { text-decoration: underline; }
        .comment-text { font-size: 13px; color: #444; line-height: 1.5; margin-top: 2px; }
        .comment-meta {
            display: flex; gap: 12px; align-items: center;
            margin-top: 6px;
        }
        .comment-time { font-size: 11px; color: #bbb; }
        .reply-btn {
            font-size: 11px; font-weight: 700; color: #999;
            background: none; border: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif; padding: 0;
        }
        .reply-btn:hover { color: var(--tc); }

        /* ── FORM COMMENTO ── */
        .comment-form-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #fff; border-top: 1px solid #eee;
            padding: 10px 16px; display: flex; gap: 10px;
            align-items: flex-end; z-index: 100; max-width: 480px;
            margin: 0 auto;
        }
        .comment-input-wrap { flex: 1; position: relative; }
        #reply-indicator {
            font-size: 11px; color: var(--tc); font-weight: 600;
            margin-bottom: 4px; display: none;
        }
        #reply-indicator button {
            background: none; border: none; color: #999;
            cursor: pointer; font-size: 13px; margin-left: 6px;
        }
        .comment-input {
            width: 100%; padding: 10px 14px;
            border: 2px solid #f0f0f0; border-radius: 22px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            resize: none; max-height: 100px; overflow-y: auto;
            transition: border-color .2s; line-height: 1.4;
        }
        .comment-input:focus { outline: none; border-color: var(--or); }
        .comment-avatar-sm {
            width: 34px; height: 34px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or); flex-shrink: 0;
        }
        .comment-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
        .send-btn {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--tc); border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0; transition: opacity .2s;
        }
        .send-btn:hover { opacity: .88; }

        .no-comments {
            padding: 32px 16px; text-align: center; color: #bbb; font-size: 14px;
        }

        .error-box {
            margin: 10px 16px; background: #fff1f0; color: #d85140;
            padding: 10px 14px; border-radius: 10px; font-size: 13px;
            border: 1px solid #ffa39e;
        }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            position: fixed; bottom: 58px; left: 0; right: 0;
            display: none; /* nascosta su questa pagina, c'è la comment bar */
        }

        /* delete post (solo autore o admin) */
        .delete-btn {
            background: none; border: none; cursor: pointer;
            color: #d85140; font-size: 13px; font-family: 'DM Sans', sans-serif;
            padding: 0; margin-left: auto;
        }
        .delete-btn:hover { text-decoration: underline; }

        /* elimina commento (autore commento o admin) */
        .delete-comment-btn {
            font-size: 11px; font-weight: 700; color: #d85140;
            background: none; border: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif; padding: 0;
            opacity: 0.7; transition: opacity .15s;
        }
        .delete-comment-btn:hover { opacity: 1; text-decoration: underline; }

        /* Badge admin visibile accanto allo username */
        .admin-badge {
            display: inline-block; font-size: 10px; font-weight: 700;
            background: #C1440E; color: #fff; border-radius: 4px;
            padding: 1px 5px; margin-left: 4px; vertical-align: middle;
            letter-spacing: .3px;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="javascript:history.back()" class="nav-back">←</a>
    <span class="nav-title">
        <?= htmlspecialchars($post['title_work']) ?>
        <?php if ($isAdmin): ?><span class="admin-badge">ADMIN</span><?php endif; ?>
    </span>
    <?php if ($post['user_id'] === $currentUserId || $isAdmin): ?>
        <button class="delete-btn" onclick="deletePost(<?= $postId ?>)">🗑 Elimina</button>
    <?php endif; ?>
</nav>

<div class="post-wrap">

    <!-- Header autore -->
    <div class="post-header">
        <div class="post-avatar">
            <img src="<?= htmlspecialchars(avatarSrc($avatar)) ?>"
                 onerror="this.src='../img/default_avatar.png'"
                 alt="@<?= htmlspecialchars($post['username']) ?>">
        </div>
        <div>
            <a href="profile.php?user=<?= urlencode($post['username']) ?>" class="post-username">
                @<?= htmlspecialchars($post['username']) ?>
            </a>
            <div class="post-date"><?= timeAgo($post['created_at']) ?></div>
        </div>
        <?php if (!empty($post['cuisine_type'])): ?>
            <span class="post-cuisine-badge">🍽️ <?= htmlspecialchars($post['cuisine_type']) ?></span>
        <?php endif; ?>
    </div>

    <!-- Immagine -->
    <?php if (!empty($post['image_path'])): ?>
        <img class="post-image"
             src="../img/uploads/foto/<?= htmlspecialchars($post['image_path']) ?>"
             alt="<?= htmlspecialchars($post['title_work']) ?>">
    <?php else: ?>
        <div class="post-image-placeholder">🍽️</div>
    <?php endif; ?>

    <!-- Azioni -->
    <div class="post-actions">
        <?php if (!$isGuest): ?>
        <button class="action-btn <?= $liked ? 'liked' : '' ?>"
                id="like-btn" onclick="toggleLike(<?= $postId ?>)">
            <span class="icon"><?= $liked ? '❤️' : '🤍' ?></span>
        </button>
        <?php else: ?>
            <span class="action-btn"><span class="icon">🤍</span></span>
        <?php endif; ?>

        <span class="action-btn"><span class="icon">💬</span> <?= $post['comments_count'] ?></span>

        <div class="stars-row">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star" style="color:<?= $i <= $post['rating'] ? 'var(--or)' : '#ddd' ?>">★</span>
            <?php endfor; ?>
        </div>
    </div>

    <div class="post-likes" id="likes-count">
        <?= $post['likes_count'] ?> mi piace
    </div>

    <!-- Titolo + Ricetta -->
    <div class="post-content">
        <div class="dish-title">📖 <?= htmlspecialchars($post['title_work']) ?></div>
        <div class="recipe-text"><?= htmlspecialchars($post['content']) ?></div>
    </div>

    <!-- Sezione commenti -->
    <div class="comments-section" id="comments">
        <div class="comments-header">
            💬 Commenti (<?= $post['comments_count'] ?>)
        </div>

        <?php if ($commentError): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($commentError) ?></div>
        <?php endif; ?>

        <?php if (empty($roots)): ?>
            <div class="no-comments">Nessun commento ancora. Sii il primo! 👨‍🍳</div>
        <?php else: ?>

            <?php foreach ($roots as $rootId => $c): ?>
                <!-- Commento root -->
                <div class="comment" id="comment-<?= $c['id'] ?>">
                    <div class="comment-avatar">
                        <img src="<?= htmlspecialchars(avatarSrc($c['avatar_url'] ?? 'default_avatar.png')) ?>"
                             onerror="this.src='../img/default_avatar.png'"
                             alt="@<?= htmlspecialchars($c['username']) ?>">
                    </div>
                    <div class="comment-body">
                        <a href="profile.php?user=<?= urlencode($c['username']) ?>" class="comment-user">
                            @<?= htmlspecialchars($c['username']) ?>
                        </a>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                        <div class="comment-meta">
                            <span class="comment-time"><?= timeAgo($c['created_at']) ?></span>
                            <?php if (!$isGuest): ?>
                                <button class="reply-btn"
                                        onclick="setReply(<?= $c['id'] ?>, '<?= htmlspecialchars($c['username']) ?>')">
                                    Rispondi
                                </button>
                            <?php endif; ?>
                            <?php if ($c['user_id'] === $currentUserId || $isAdmin): ?>
                                <button class="delete-comment-btn"
                                        onclick="deleteComment(<?= $c['id'] ?>)">
                                    🗑 Elimina
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Risposte al commento -->
                <?php if (!empty($replies[$rootId])): ?>
                    <?php foreach ($replies[$rootId] as $r): ?>
                    <div class="comment reply" id="comment-<?= $r['id'] ?>">
                        <div class="comment-avatar">
                            <img src="<?= htmlspecialchars(avatarSrc($r['avatar_url'] ?? 'default_avatar.png')) ?>"
                                 onerror="this.src='../img/default_avatar.png'"
                                 alt="@<?= htmlspecialchars($r['username']) ?>">
                        </div>
                        <div class="comment-body">
                            <a href="profile.php?user=<?= urlencode($r['username']) ?>" class="comment-user">
                                @<?= htmlspecialchars($r['username']) ?>
                            </a>
                            <div class="comment-text"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
                            <div class="comment-meta">
                                <span class="comment-time"><?= timeAgo($r['created_at']) ?></span>
                                <?php if ($r['user_id'] === $currentUserId || $isAdmin): ?>
                                    <button class="delete-comment-btn"
                                            onclick="deleteComment(<?= $r['id'] ?>)">
                                        🗑 Elimina
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- FORM COMMENTO FISSO IN BASSO -->
<?php if (!$isGuest): ?>
<div class="comment-form-bar">
    <div class="comment-avatar-sm">
        <img src="<?= htmlspecialchars(avatarSrc($currentAvatar)) ?>"
             onerror="this.src='../img/default_avatar.png'"
             alt="tu">
    </div>
    <div class="comment-input-wrap">
        <div id="reply-indicator">
            <span id="reply-label"></span>
            <button onclick="clearReply()">✕</button>
        </div>
        <form method="POST" id="comment-form" style="display:flex;gap:8px;align-items:flex-end">
            <input type="hidden" name="parent_id" id="parent-id-input" value="">
            <textarea name="comment" id="comment-input" class="comment-input"
                      placeholder="Scrivi un commento..." rows="1"
                      maxlength="500"></textarea>
            <button type="submit" class="send-btn">➤</button>
        </form>
    </div>
</div>
<?php else: ?>
<div style="position:fixed;bottom:0;left:0;right:0;background:var(--cr);
            border-top:1px solid #f0d9c0;padding:12px 16px;text-align:center;
            font-size:13px;color:var(--br)">
    <a href="../backend/login/registration.php"
       style="color:var(--tc);font-weight:700;text-decoration:none">Registrati</a>
    per lasciare un commento
</div>
<?php endif; ?>

<script>
// ── LIKE ──
function toggleLike(postId) {
    fetch('../backend/api/like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ post_id: postId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const btn  = document.getElementById('like-btn');
            const icon = btn.querySelector('.icon');
            icon.textContent = data.liked ? '❤️' : '🤍';
            btn.classList.toggle('liked', data.liked);
            document.getElementById('likes-count').textContent = data.likes_count + ' mi piace';
        }
    });
}

// ── RISPONDI ──
function setReply(commentId, username) {
    document.getElementById('parent-id-input').value = commentId;
    document.getElementById('reply-label').textContent = '↩ Risposta a @' + username;
    document.getElementById('reply-indicator').style.display = 'block';
    document.getElementById('comment-input').focus();
    document.getElementById('comment-input').placeholder = 'Rispondi a @' + username + '...';
}

function clearReply() {
    document.getElementById('parent-id-input').value = '';
    document.getElementById('reply-indicator').style.display = 'none';
    document.getElementById('comment-input').placeholder = 'Scrivi un commento...';
}

// ── AUTO-RESIZE TEXTAREA ──
const textarea = document.getElementById('comment-input');
if (textarea) {
    textarea.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
}

// ── ELIMINA COMMENTO (autore o admin) ──
function deleteComment(commentId) {
    if (!confirm('Eliminare questo commento? L\'operazione non è reversibile.')) return;

    fetch('../backend/api/delete_comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ comment_id: commentId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Rimuove il commento (e le sue risposte indentate) dal DOM
            const el = document.getElementById('comment-' + commentId);
            if (el) el.remove();
        } else {
            alert('Errore: ' + (data.error ?? 'sconosciuto'));
        }
    })
    .catch(() => alert('Errore di rete, riprova.'));
}


//     if (!confirm('Sei sicuro di voler eliminare questo post?')) return;
//     fetch('../backend/api/delete_post.php', {
//         method: 'POST',
//         headers: { 'Content-Type': 'application/json' },
//         body: JSON.stringify({ post_id: postId })
//     })
//     .then(r => r.json())
//     .then(data => {
//         if (data.success) {
//             window.location.href = 'profile.php?user=<?= urlencode($currentUsername) ?>';
//         } else {
//             alert('Errore durante l\'eliminazione.');
//         }
//     });
// }
/**
 * Funzione per eliminare il post via AJAX
 */
function deletePost(postId) {
    // Messaggio di conferma
    if (!confirm('Sei sicuro di voler eliminare definitivamente questo post? L\'operazione non è reversibile.')) {
        return;
    }

    // Mostriamo un log in console per debug
    console.log("Invio richiesta eliminazione per il post:", postId);

    fetch('../backend/api/delete_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ post_id: postId })
    })
    .then(response => {
        // Se il server risponde con un errore di file (es 404 o 500)
        if (!response.ok) {
            throw new Error('Errore di connessione al server (Status: ' + response.status + ')');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Post eliminato correttamente.');
            // Reindirizziamo al feed o al profilo
            window.location.href = 'feed.php';
        } else {
            // Mostriamo l'errore specifico restituito dal PHP
            alert('Impossibile eliminare: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Errore:', error);
        alert('Si è verificato un errore tecnico. Controlla la console del browser.');
    });
}
</script>

</body>
</html>
