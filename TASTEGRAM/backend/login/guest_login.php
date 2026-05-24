<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/Database.php';

$db   = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT id, username, avatar_url FROM users WHERE username = 'ospite' LIMIT 1");
$stmt->execute();
$guest = $stmt->fetch(PDO::FETCH_ASSOC);

if ($guest) {
    $_SESSION['user_id']    = (int) $guest['id'];
    $_SESSION['username']   = $guest['username'];
    $_SESSION['avatar_url'] = $guest['avatar_url'] ?? 'default_avatar.png';
    header('Location: ../../frontend/feed.php');
} else {
    header('Location: login.php?error=guest');
}
exit;
