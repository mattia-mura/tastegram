<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

if ($isGuest) { header('Location: ../backend/login/registration.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    $rating  = (int) ($_POST['rating'] ?? 0);
    $cuisine = trim($_POST['cuisine'] ?? '');
    $imagePath = null;

    if (empty($title) || empty($content)) {
        $error = 'Il nome del piatto e la ricetta sono obbligatori.';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Seleziona una valutazione tra 1 e 5 stelle.';
    } else {
        // Upload immagine
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['photo'];
            $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Immagine troppo grande. Massimo 5MB.';
            } elseif (!in_array($mimeType, $allowed)) {
                $error = 'Formato non supportato. Usa JPG, PNG o WebP.';
            } else {
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'post_' . $currentUserId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                // Path assoluto usando DOCUMENT_ROOT — funziona su XAMPP, MAMP e server Linux
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/tastegram/img/uploads/foto/';

                // Crea la cartella se non esiste
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $imagePath = $filename;
                } else {
                    $error = 'Errore durante il caricamento. Controlla i permessi di img/uploads/foto/';
                }
            }
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Errore PHP upload (es. file troppo grande per php.ini)
            $phpErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File troppo grande (limite php.ini).',
                UPLOAD_ERR_FORM_SIZE  => 'File troppo grande (limite form).',
                UPLOAD_ERR_PARTIAL    => 'Upload incompleto, riprova.',
                UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante.',
                UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere su disco.',
            ];
            $error = $phpErrors[$_FILES['photo']['error']] ?? 'Errore upload sconosciuto.';
        }

        if (empty($error)) {
            $stmt = $sql->prepare("
                INSERT INTO posts (user_id, title_work, content, rating, cuisine_type, image_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$currentUserId, $title, $content, $rating, $cuisine ?: null, $imagePath]);

            $newPostId = (int) $sql->lastInsertId();
            header('Location: post.php?id=' . $newPostId);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuovo Post — Tastegram</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --tc:#C1440E; --or:#E2621B; --cr:#FDF6EE; --br:#3D1A06; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #fafafa; font-family: 'DM Sans', sans-serif; padding-bottom: 40px; }
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
        .nav-title { font-size: 16px; font-weight: 700; color: var(--br); flex: 1; text-align: center; }
        .btn-publish {
            padding: 7px 18px; background: var(--tc); color: #fff; border: none;
            border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: opacity .2s;
        }
        .btn-publish:disabled { opacity: .5; cursor: not-allowed; }
        .form-wrap { max-width: 480px; margin: 0 auto; padding: 16px; }
        .error-box {
            background: #fff1f0; color: #d85140; padding: 12px 16px;
            border-radius: 12px; font-size: 13px; margin-bottom: 16px;
            border: 1px solid #ffa39e;
        }
        .photo-upload {
            width: 100%; aspect-ratio: 1/1; border-radius: 16px;
            border: 2px dashed #f0c090; background: var(--cr);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; margin-bottom: 20px; overflow: hidden; position: relative;
        }
        .photo-upload:hover { border-color: var(--or); }
        .photo-upload input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; font-size: 0; }
        .photo-placeholder { text-align: center; color: #c0845a; pointer-events: none; }
        .photo-placeholder .ph-icon { font-size: 44px; margin-bottom: 8px; }
        .photo-placeholder p { font-size: 14px; font-weight: 500; }
        .photo-placeholder small { font-size: 11px; color: #d4a070; margin-top: 4px; display: block; }
        #photo-preview { width:100%; height:100%; object-fit:cover; display:none; position:absolute; inset:0; }
        .remove-photo {
            position: absolute; top: 10px; right: 10px; width: 30px; height: 30px;
            border-radius: 50%; background: rgba(0,0,0,0.55); color: #fff;
            border: none; font-size: 16px; cursor: pointer; display: none;
            align-items: center; justify-content: center; z-index: 2;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px; color: var(--br); margin-bottom: 8px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 13px 16px; border: 2px solid #f0f0f0;
            border-radius: 14px; font-size: 15px; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s; background: #fff; color: var(--br);
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none; border-color: var(--or); background: var(--cr);
        }
        .form-group textarea { height: 140px; resize: none; line-height: 1.6; }
        .form-row { display: flex; gap: 12px; }
        .form-row .form-group { flex: 1; }
        .char-count { font-size: 11px; color: #bbb; text-align: right; margin-top: 4px; }
        /* Stelle */
        .stars-wrap { display: flex; flex-direction: row-reverse; gap: 4px; padding: 4px 0; }
        .stars-wrap input[type=radio] { display: none; }
        .stars-wrap label { font-size: 30px; cursor: pointer; color: #ddd; transition: color .15s; }
        .stars-wrap input:checked ~ label { color: var(--or); }
        .stars-wrap label:hover, .stars-wrap label:hover ~ label { color: var(--or); }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="feed.php" class="nav-back">✕</a>
    <span class="nav-title">Nuovo post</span>
    <button class="btn-publish" id="btn-publish" form="post-form" type="submit">Pubblica</button>
</nav>

<div class="form-wrap">
    <?php if ($error): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="post-form" method="POST" enctype="multipart/form-data">
        <!-- Upload foto -->
        <div class="photo-upload" id="photo-drop">
            <input type="file" name="photo" id="photo-input" accept="image/jpeg,image/png,image/webp">
            <img id="photo-preview" src="" alt="Anteprima">
            <button type="button" class="remove-photo" id="remove-photo">✕</button>
            <div class="photo-placeholder" id="photo-placeholder">
                <div class="ph-icon">📷</div>
                <p>Tocca per aggiungere la foto del piatto</p>
                <small>JPG, PNG o WebP · max 5MB</small>
            </div>
        </div>

        <div class="form-group">
            <label>Nome del piatto</label>
            <input type="text" name="title" placeholder="Es. Risotto alla Milanese"
                   maxlength="255" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Ricetta</label>
            <textarea name="content" id="content-input"
                      placeholder="Ingredienti, procedimento, consigli... 🍴"
                      maxlength="2000" required><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            <div class="char-count"><span id="char-num">0</span> / 2000</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Tipo di cucina</label>
                <select name="cuisine">
                    <option value="">— Seleziona —</option>
                    <?php
                    $cuisines = ['Italiana','Giapponese','Messicana','Indiana',
                                 'Cinese','Francese','Greca','Spagnola',
                                 'Mediterranea','Fusion','Vegana','Altro'];
                    foreach ($cuisines as $c):
                        $sel = (($_POST['cuisine'] ?? '') === $c) ? 'selected' : '';
                    ?>
                        <option value="<?= $c ?>" <?= $sel ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Valutazione</label>
                <div class="stars-wrap">
                    <?php for ($i = 5; $i >= 1; $i--):
                        $checked = (($_POST['rating'] ?? 0) == $i) ? 'checked' : '';
                    ?>
                        <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" <?= $checked ?> required>
                        <label for="star<?= $i ?>">★</label>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const photoInput      = document.getElementById('photo-input');
const photoPreview    = document.getElementById('photo-preview');
const photoPlaceholder = document.getElementById('photo-placeholder');
const removePhoto     = document.getElementById('remove-photo');

photoInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        photoPreview.src = e.target.result;
        photoPreview.style.display = 'block';
        photoPlaceholder.style.display = 'none';
        removePhoto.style.display = 'flex';
    };
    reader.readAsDataURL(file);
});

removePhoto.addEventListener('click', function (e) {
    e.stopPropagation();
    photoInput.value = '';
    photoPreview.style.display = 'none';
    photoPlaceholder.style.display = 'block';
    removePhoto.style.display = 'none';
});

const contentInput = document.getElementById('content-input');
const charNum      = document.getElementById('char-num');
contentInput.addEventListener('input', () => {
    charNum.textContent = contentInput.value.length;
});

document.getElementById('post-form').addEventListener('submit', function () {
    const btn = document.getElementById('btn-publish');
    btn.disabled = true;
    btn.textContent = 'Pubblicazione...';
});
</script>
</body>
</html>
