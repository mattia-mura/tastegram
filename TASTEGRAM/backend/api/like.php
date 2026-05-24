<?php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

foreach ([
    __DIR__ . '/../../config/Database.php',
    __DIR__ . '/../config/Database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/tastegram/config/Database.php',
] as $path) {
    if (file_exists($path)) { require_once $path; break; }
}

if (!class_exists('Database')) {
    echo json_encode(['success' => false, 'error' => 'Database non disponibile']);
    exit;
}

$sql    = Database::getInstance()->getConnection();
$data   = json_decode(file_get_contents('php://input'), true);
$postId = (int) ($data['post_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if ($postId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Post non valido']);
    exit;
}

$check = $sql->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
$check->execute([$postId, $userId]);
$alreadyLiked = (bool) $check->fetchColumn();

if ($alreadyLiked) {
    $sql->prepare("DELETE FROM likes WHERE post_id = ? AND user_id = ?")->execute([$postId, $userId]);
    $liked = false;
} else {
    $sql->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)")->execute([$postId, $userId]);

    // Notifica al proprietario (solo se non è il proprio post)
    $owner = $sql->prepare("SELECT user_id FROM posts WHERE id = ?");
    $owner->execute([$postId]);
    $ownerId = (int) $owner->fetchColumn();
    if ($ownerId && $ownerId !== $userId) {
        $sql->prepare("INSERT INTO notifications (user_id, actor_id, post_id, type) VALUES (?, ?, ?, 'like')")
            ->execute([$ownerId, $userId, $postId]);
    }
    $liked = true;
}

$count = $sql->prepare("SELECT likes_count FROM posts WHERE id = ?");
$count->execute([$postId]);

echo json_encode([
    'success'     => true,
    'liked'       => $liked,
    'likes_count' => (int) $count->fetchColumn(),
]);
