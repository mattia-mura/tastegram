<?php
/**
 * API per l'eliminazione di un commento
 * Percorso: /tastegram/backend/api/delete_comment.php
 *
 * Permessi:
 *   - Autore del commento: può eliminare i propri commenti
 *   - Admin (role = 'admin'): può eliminare qualsiasi commento
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// 1. Controllo autenticazione
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Devi effettuare il login.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/Database.php';
    $sql    = Database::getInstance()->getConnection();
    $userId = (int) $_SESSION['user_id'];

    // 2. Verifica ruolo admin
    $roleStmt = $sql->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$userId]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $isAdmin = ($roleRow && (int)$roleRow['role'] === 1);

    // 3. Input
    $data      = json_decode(file_get_contents('php://input'), true);
    $commentId = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;

    if ($commentId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID commento non valido.']);
        exit;
    }

    // 4. Recupero commento (verifica esistenza e proprietà)
    if ($isAdmin) {
        $stmt = $sql->prepare("SELECT id, post_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
    } else {
        $stmt = $sql->prepare("SELECT id, post_id FROM comments WHERE id = ? AND user_id = ?");
        $stmt->execute([$commentId, $userId]);
    }
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        echo json_encode(['success' => false, 'error' => 'Commento non trovato o non hai i permessi.']);
        exit;
    }

    // 5. Elimina il commento e le sue risposte (figli con parent_id = commentId)
    //    Prima i figli, poi il root (o semplicemente un DELETE con OR)
    $sql->prepare("DELETE FROM comments WHERE id = ? OR parent_id = ?")
        ->execute([$commentId, $commentId]);

    // 6. Aggiorna il contatore commenti sul post
    //    (se non hai un trigger che lo fa automaticamente)
    $sql->prepare("
        UPDATE posts
        SET comments_count = (SELECT COUNT(*) FROM comments WHERE post_id = ?)
        WHERE id = ?
    ")->execute([$comment['post_id'], $comment['post_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('delete_comment error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Errore server: ' . $e->getMessage()]);
}
