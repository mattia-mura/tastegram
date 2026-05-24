<?php
/**
 * API per l'eliminazione di un post
 * Percorso: /tastegram/backend/api/delete_post.php
 *
 * Permessi:
 *   - Autore del post: può eliminare i propri post
 *   - Admin (role = 'admin'): può eliminare qualsiasi post
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

// 1. Controllo autenticazione
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Devi effettuare il login per eliminare un post.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/Database.php';
    $sql    = Database::getInstance()->getConnection();
    $userId = (int) $_SESSION['user_id'];

    // 2. Verifica se l'utente è admin
    $roleStmt = $sql->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $roleStmt->execute([$userId]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $isAdmin = ($roleRow && (int)$roleRow['role'] === 1);

    // 3. Ricezione dati JSON
    $data   = json_decode(file_get_contents('php://input'), true);
    $postId = isset($data['post_id']) ? (int)$data['post_id'] : 0;

    if ($postId <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID post non valido.']);
        exit;
    }

    // 4. Recupero info post
    //    - Admin: può prendere qualsiasi post
    //    - Utente normale: solo i propri
    if ($isAdmin) {
        $stmt = $sql->prepare("SELECT image_path, user_id FROM posts WHERE id = ?");
        $stmt->execute([$postId]);
    } else {
        $stmt = $sql->prepare("SELECT image_path, user_id FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
    }
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'Post non trovato o non hai i permessi per eliminarlo.']);
        exit;
    }

    // 5. Eliminazione file immagine dal server
    if (!empty($post['image_path'])) {
        $filename = basename($post['image_path']);
        $filePath = __DIR__ . '/../../img/uploads/foto/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // 6. Eliminazione dal DB (CASCADE elimina commenti, like, notifiche)
    if ($isAdmin) {
        $sql->prepare("DELETE FROM posts WHERE id = ?")->execute([$postId]);
    } else {
        $sql->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?")->execute([$postId, $userId]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('delete_post error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Errore server: ' . $e->getMessage()]);
}
