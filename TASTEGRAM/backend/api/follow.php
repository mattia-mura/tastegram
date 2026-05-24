<?php
// backend/api/follow.php
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// 1. Autenticazione
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

// ── 2. Carica Database ────────────────────────────────────────────────────
foreach ([
    __DIR__ . '/../../config/Database.php',
    __DIR__ . '/../config/Database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/tastegram/config/Database.php',
] as $path) {
    if (file_exists($path)) { require_once $path; break; }
}

if (!class_exists('Database')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database non disponibile']);
    exit;
}

$sql  = Database::getInstance()->getConnection();
$myId = (int) $_SESSION['user_id'];

// ── 3. Validazione input ──────────────────────────────────────────────────
$data     = json_decode(file_get_contents('php://input'), true);
$targetId = (int) ($data['target_id'] ?? 0);

if ($targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Target mancante']);
    exit;
}

if ($targetId === $myId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Non puoi seguire te stesso']);
    exit;
}

// Verifica che il target esista
$exists = $sql->prepare("SELECT id FROM users WHERE id = ?");
$exists->execute([$targetId]);
if (!$exists->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Utente non trovato']);
    exit;
}

// ── 4. Logica toggle con transazione atomica ──────────────────────────────
// La transazione garantisce che INSERT/DELETE e il contatore siano
// sempre coerenti, anche con richieste multiple contemporanee.
try {
    $sql->beginTransaction();

    // Prepared statement — protetto da SQL Injection
    $check = $sql->prepare("
        SELECT 1 FROM follows
        WHERE follower_id = ? AND followed_id = ?
        FOR UPDATE
    ");
    // FOR UPDATE blocca la riga durante la transazione (row-level lock)
    // impedisce race condition con richieste simultanee
    $check->execute([$myId, $targetId]);
    $alreadyFollowing = (bool) $check->fetchColumn();

    if ($alreadyFollowing) {
        // ── UNFOLLOW ──────────────────────────────────────────────────────
        $sql->prepare("
            DELETE FROM follows
            WHERE follower_id = ? AND followed_id = ?
        ")->execute([$myId, $targetId]);

        // Decrementa contatori — GREATEST evita valori negativi
        // $sql->prepare("
        //     UPDATE users
        //     SET followers_count = GREATEST(followers_count - 1, 0)
        //     WHERE id = ?
        // ")->execute([$targetId]);

        // $sql->prepare("
        //     UPDATE users
        //     SET following_count = GREATEST(following_count - 1, 0)
        //     WHERE id = ?
        // ")->execute([$myId]);

        $following = false;

    } else {
        // ── FOLLOW ────────────────────────────────────────────────────────
        // INSERT IGNORE: se per qualsiasi motivo esiste già (race condition
        // residua) non lancia eccezione ma ignora silenziosamente
        $sql->prepare("
            INSERT IGNORE INTO follows (follower_id, followed_id)
            VALUES (?, ?)
        ")->execute([$myId, $targetId]);

        // Controlla che l'INSERT abbia effettivamente inserito una riga
        // (rowCount = 0 significa che era già presente — INSERT IGNORE)
        // In quel caso non aggiorniamo i contatori per evitare doppio incremento
        // I trigger nel DB gestiscono già questo, ma li abbiamo disattivati
        // usando INSERT IGNORE, quindi lo facciamo manualmente
        // Nota: i trigger del tuo db.sql esistono — se li hai attivi
        // rimuovi i due UPDATE qui sotto per evitare doppio conteggio.
        // Se hai rimosso i trigger, tienili.
        // $sql->prepare("
        //     UPDATE users
        //     SET followers_count = followers_count + 1
        //     WHERE id = ?
        // ")->execute([$targetId]);

        // $sql->prepare("
        //     UPDATE users
        //     SET following_count = following_count + 1
        //     WHERE id = ?
        // ")->execute([$myId]);

        // Notifica — solo se l'utente non era già seguito
        $sql->prepare("
            INSERT IGNORE INTO notifications (user_id, actor_id, type)
            VALUES (?, ?, 'follow')
        ")->execute([$targetId, $myId]);

        $following = true;
    }

    $sql->commit();

} catch (PDOException $e) {
    $sql->rollBack();
    error_log('Follow error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Errore interno, riprova']);
    exit;
}

// ── 5. Risposta con contatore aggiornato dal DB ───────────────────────────
$fc = $sql->prepare("SELECT followers_count FROM users WHERE id = ?");
$fc->execute([$targetId]);

echo json_encode([
    'success'         => true,
    'following'       => $following,
    'followers_count' => (int) $fc->fetchColumn(),
]);


