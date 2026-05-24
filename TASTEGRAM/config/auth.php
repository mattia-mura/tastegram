<?php
// config/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /tastegram/backend/login/login.php');
    exit;
}

$currentUserId   = (int)    $_SESSION['user_id'];
$currentUsername =          $_SESSION['username']   ?? 'utente';
$currentAvatar   =          $_SESSION['avatar_url'] ?? 'default_avatar.png';
$isGuest         = ($currentUsername === 'ospite');

// ── Ruolo admin ──────────────────────────────────────────────────────────────
// Verifica ogni volta dal DB per sicurezza (non ci fidiamo solo della sessione)
$isAdmin = false;
if (!$isGuest) {
    // Lazy-load del DB solo se non già caricato
    if (!class_exists('Database')) {
        require_once __DIR__ . '/Database.php';
    }
    $_adminStmt = Database::getInstance()->getConnection()
        ->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $_adminStmt->execute([$currentUserId]);
    $_adminRow = $_adminStmt->fetch();
    $isAdmin   = ($_adminRow && (int)$_adminRow['role'] === 1);
    unset($_adminStmt, $_adminRow);
}

// Include helpers — disponibili in tutte le pagine protette
require_once __DIR__ . '/helpers.php';
