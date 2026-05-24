<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Percorsi corretti per caricare i file (aggiustali se la tua struttura è diversa)
require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/Database.php';
$sql = Database::getInstance()->getConnection();

// Solo utenti registrati
if ($isGuest) { header('Location: ../backend/login/registration.php'); exit; }

$itemId = (int) ($_GET['item'] ?? 0);
if ($itemId <= 0) { header('Location: explore.php?tab=shop'); exit; }

// Carica articolo + email venditore
$stmt = $sql->prepare("
    SELECT s.id, s.title, s.image_path, s.price,
           u.id AS seller_id, u.username AS seller_username,
           u.email AS seller_email, u.avatar_url AS seller_avatar
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

// Non puoi contattare te stesso
if ($item['seller_id'] === $currentUserId) {
    header('Location: shop_item.php?id=' . $itemId);
    exit;
}

// Carica email dell'acquirente (utente loggato)
$meStmt = $sql->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
$meStmt->execute([$currentUserId]);
$me = $meStmt->fetch();

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $phone   = trim($_POST['phone']   ?? '');

    if (empty($subject)) {
        $error = 'L\'oggetto è obbligatorio.';
    } elseif (empty($message)) {
        $error = 'Il messaggio non può essere vuoto.';
    } elseif (mb_strlen($message) > 2000) {
        $error = 'Messaggio troppo lungo (max 2000 caratteri).';
    } else {
        // ── Costruzione email ────────────────────────────────────────────
        $sellerEmail = $item['seller_email'];
        $buyerName   = $me['username'];
        $buyerEmail  = $me['email'];
        $itemTitle   = $item['title'];
        $itemPrice   = number_format((float)$item['price'], 2, ',', '.');
        $itemUrl     = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://'
                       . $_SERVER['HTTP_HOST']
                       . '/tastegram/frontend/shop_item.php?id=' . $itemId;

        $emailSubject = '[Tastegram Shop] ' . $subject;

        // Corpo email in HTML
        $htmlBody = '<!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8">
<style>
  body{font-family:\'Segoe UI\',sans-serif;background:#fafafa;margin:0;padding:0;}
  .wrap{max-width:520px;margin:30px auto;background:#fff;border-radius:16px;
        overflow:hidden;border:1px solid #f0e8de;}
  .header{background:#C1440E;padding:20px 24px;color:#fff;}
  .header h1{font-size:20px;margin:0 0 4px;}
  .header p{font-size:13px;margin:0;opacity:.85;}
  .body{padding:24px;}
  .item-box{display:flex;gap:14px;background:#FDF6EE;border-radius:12px;
            padding:14px;margin-bottom:20px;align-items:center;}
  .item-box img{width:64px;height:64px;border-radius:10px;object-fit:cover;background:#f0e0d0;}
  .item-title{font-size:15px;font-weight:700;color:#3D1A06;margin-bottom:4px;}
  .item-price{font-size:14px;color:#C1440E;font-weight:700;}
  .section-label{font-size:11px;font-weight:700;text-transform:uppercase;
                 letter-spacing:.5px;color:#bbb;margin-bottom:8px;}
  .message-box{background:#f9f9f9;border-radius:12px;padding:16px;
               font-size:14px;color:#333;line-height:1.7;white-space:pre-line;
               border:1px solid #f0f0f0;margin-bottom:20px;}
  .reply-btn{display:inline-block;padding:12px 24px;background:#C1440E;color:#fff;
             border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;}
  .footer{padding:16px 24px;background:#fafafa;border-top:1px solid #f0f0f0;
          font-size:12px;color:#bbb;text-align:center;}
  .contact-info{font-size:13px;color:#555;margin-bottom:16px;}
  .contact-info span{font-weight:700;color:#3D1A06;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>🛒 Nuovo messaggio per il tuo annuncio</h1>
    <p>Qualcuno è interessato al tuo articolo su Tastegram</p>
  </div>
  <div class="body">
    <div class="item-box">
      <div style="font-size:36px">🛒</div>
      <div>
        <div class="item-title">' . htmlspecialchars($itemTitle) . '</div>
        <div class="item-price">€' . $itemPrice . '</div>
      </div>
    </div>

    <div class="section-label">Da</div>
    <div class="contact-info">
      <span>@' . htmlspecialchars($buyerName) . '</span> — ' . htmlspecialchars($buyerEmail) . '
      ' . ($phone ? '<br>📞 <span>' . htmlspecialchars($phone) . '</span>' : '') . '
    </div>

    <div class="section-label">Oggetto</div>
    <div class="contact-info"><span>' . htmlspecialchars($subject) . '</span></div>

    <div class="section-label">Messaggio</div>
    <div class="message-box">' . nl2br(htmlspecialchars($message)) . '</div>

    <a href="mailto:' . htmlspecialchars($buyerEmail) . '?subject=Re: ' . rawurlencode($emailSubject) . '" class="reply-btn">
      ✉️ Rispondi a @' . htmlspecialchars($buyerName) . '
    </a>

    <p style="margin-top:16px;font-size:12px;color:#bbb">
      Oppure vai direttamente all\'annuncio:
      <a href="' . $itemUrl . '" style="color:#C1440E">' . $itemUrl . '</a>
    </p>
  </div>
  <div class="footer">Tastegram · Non rispondere a questa email automatica</div>
</div>
</body>
</html>';

        // Headers per email HTML
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Tastegram Shop <noreply@tastegram.local>\r\n";
        $headers .= "Reply-To: " . $buyerName . " <" . $buyerEmail . ">\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
    
        // ── SOLUZIONE FANTASMA (Simulazione invio) ────────────────────────
        
        // Usiamo @ per silenziare il Warning di XAMPP se il server mail non c'è
        // $mailSent = @mail($sellerEmail, $emailSubject, $htmlBody, $headers);

        // if ($mailSent) {
        //     // Se per miracolo il server è configurato, invia davvero
        //     $success = true;
        // } else {
        //     // Se fallisce (tipico di XAMPP), facciamo finta che sia andata bene
        //     // Qui puoi decidere se loggare l'errore in un file o lasciarlo vuoto
        //     $logError = "Email simulata: Destinatario $sellerEmail - Oggetto: $emailSubject";
            
        //     // Forziamo il successo per l'utente finale
        //     $error   = '';
        //     $success = true; 
        // }
        // ── FINE SOLUZIONE FANTASMA

        // ── INVIO CON PHPMAILER ────────────────────────────────────────── Funzinante
        $mail = new PHPMailer(true);

        try {
            // Parametri del server (Esempio per Gmail)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tastegram67@gmail.com'; // Inserisci la tua mail
            $mail->Password   = 'codc kbtn ytjg fuqa';   // La Password per le App (16 lettere)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            // Destinatari
            $mail->setFrom('noreply@tastegram.it', 'Tastegram Shop');
            $mail->addAddress($sellerEmail); 
            $mail->addReplyTo($buyerEmail, $buyerName); // Risposta diretta all'acquirente

            // Contenuto
            $mail->isHTML(true);
            $mail->Subject = $emailSubject;
            $mail->Body    = $htmlBody;

            $mail->send();
            $success = true;
        } catch (Exception $e) {
            // Se fallisce l'invio reale, puoi decidere se mostrare l'errore o usare il tuo fallback
            $error = "Messaggio non inviato. Errore: {$mail->ErrorInfo}";
            
            // Per test locale, se vuoi forzare il successo:
            // $error = ''; $success = true;
        }

        // ── FINE INVIO CON PHPMAILER
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contatta il venditore — Tastegram</title>
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
        .btn-send {
            padding: 7px 18px; background: var(--tc); color: #fff; border: none;
            border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: opacity .2s;
        }
        .btn-send:disabled { opacity: .5; cursor: not-allowed; }

        .form-wrap { max-width: 480px; margin: 0 auto; padding: 16px; }

        /* ── Card articolo ── */
        .item-preview {
            display: flex; align-items: center; gap: 14px;
            background: #fff; border-radius: 16px;
            padding: 14px; margin-bottom: 20px;
            border: 1px solid #f0e8de;
        }
        .item-preview-img {
            width: 64px; height: 64px; border-radius: 12px;
            overflow: hidden; background: var(--cr); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 28px;
        }
        .item-preview-img img { width: 100%; height: 100%; object-fit: cover; }
        .item-preview-info { flex: 1; }
        .item-preview-title { font-size: 14px; font-weight: 700; color: var(--br); margin-bottom: 4px; }
        .item-preview-price { font-size: 14px; font-weight: 700; color: var(--tc); }

        /* ── Card venditore ── */
        .seller-preview {
            display: flex; align-items: center; gap: 12px;
            background: #fff; border-radius: 16px;
            padding: 12px 14px; margin-bottom: 20px;
            border: 1px solid #f0e8de;
        }
        .seller-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            overflow: hidden; border: 2px solid var(--or); flex-shrink: 0;
        }
        .seller-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .seller-label { font-size: 11px; color: #bbb; font-weight: 600; text-transform: uppercase; }
        .seller-name  { font-size: 14px; font-weight: 700; color: var(--br); margin-top: 2px; }

        /* ── Form ── */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px; color: var(--br); margin-bottom: 8px;
        }
        .form-group input, .form-group textarea {
            width: 100%; padding: 13px 16px; border: 2px solid #f0f0f0;
            border-radius: 14px; font-size: 15px; font-family: 'DM Sans', sans-serif;
            transition: border-color .2s; background: #fff; color: var(--br);
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none; border-color: var(--or); background: var(--cr);
        }
        .form-group textarea { height: 180px; resize: none; line-height: 1.6; }
        .char-count { font-size: 11px; color: #bbb; text-align: right; margin-top: 4px; }
        .form-hint { font-size: 12px; color: #bbb; margin-top: 6px; }

        /* ── Messaggi ── */
        .error-box {
            background: #fff1f0; color: #d85140; padding: 12px 16px;
            border-radius: 12px; font-size: 13px; margin-bottom: 16px;
            border: 1px solid #ffa39e;
        }

        /* ── Successo ── */
        .success-wrap {
            text-align: center; padding: 48px 24px;
        }
        .success-icon { font-size: 64px; margin-bottom: 16px; }
        .success-title { font-size: 20px; font-weight: 700; color: var(--br); margin-bottom: 8px; }
        .success-desc { font-size: 14px; color: #888; line-height: 1.6; margin-bottom: 28px; }
        .btn-back-shop {
            display: inline-block; padding: 12px 28px;
            background: var(--tc); color: #fff; border-radius: 12px;
            text-decoration: none; font-weight: 700; font-size: 15px;
        }
        .btn-back-item {
            display: inline-block; padding: 12px 28px; margin-left: 10px;
            border: 1.5px solid var(--or); color: var(--tc); border-radius: 12px;
            text-decoration: none; font-weight: 600; font-size: 15px;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="shop_item.php?id=<?= $itemId ?>" class="nav-back">←</a>
    <span class="nav-title">Contatta il venditore</span>
    <?php if (!$success): ?>
        <button class="btn-send" id="btn-send" form="contact-form" type="submit">Invia</button>
    <?php else: ?>
        <div style="width:60px"></div>
    <?php endif; ?>
</nav>

<div class="form-wrap">

<?php if ($success): ?>
    <!-- ── STATO SUCCESSO ── -->
    <div class="success-wrap">
        <div class="success-icon">✅</div>
        <div class="success-title">Messaggio inviato!</div>
        <div class="success-desc">
            La tua email è stata inviata a <strong>@<?= htmlspecialchars($item['seller_username']) ?></strong>.<br>
            Il venditore ti risponderà direttamente alla tua email:<br>
            <strong><?= htmlspecialchars($me['email']) ?></strong>
        </div>
        <a href="shop_item.php?id=<?= $itemId ?>" class="btn-back-item">← Torna all'annuncio</a>
        <a href="explore.php?tab=shop" class="btn-back-shop">🛒 Shop</a>
    </div>

<?php else: ?>

    <?php if ($error): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Card articolo -->
    <div class="item-preview">
        <div class="item-preview-img">
            <?php if (!empty($item['image_path'])): ?>
                <img src="../img/uploads/shops/<?= htmlspecialchars($item['image_path']) ?>"
                     onerror="this.style.display='none'" alt="">
            <?php else: ?>
                🛒
            <?php endif; ?>
        </div>
        <div class="item-preview-info">
            <div class="item-preview-title"><?= htmlspecialchars($item['title']) ?></div>
            <div class="item-preview-price">€<?= number_format((float)$item['price'], 2, ',', '.') ?></div>
        </div>
    </div>

    <!-- Card venditore -->
    <div class="seller-preview">
        <div class="seller-avatar">
            <img src="<?= htmlspecialchars(avatarSrc($item['seller_avatar'])) ?>"
                 onerror="this.src='../img/default_avatar.png'"
                 alt="@<?= htmlspecialchars($item['seller_username']) ?>">
        </div>
        <div>
            <div class="seller-label">Stai scrivendo a</div>
            <div class="seller-name">@<?= htmlspecialchars($item['seller_username']) ?></div>
        </div>
    </div>

    <!-- Form -->
    <form id="contact-form" method="POST">

        <div class="form-group">
            <label>Oggetto</label>
            <input type="text" name="subject"
                   placeholder='Es. "Sono interessato al tuo articolo"'
                   maxlength="150" required
                   value="<?= htmlspecialchars($_POST['subject'] ?? 'Sono interessato a: ' . $item['title']) ?>">
        </div>

        <div class="form-group">
            <label>Messaggio</label>
            <textarea name="message" id="msg-input"
                      placeholder="Scrivi qui la tua domanda o proposta al venditore..."
                      maxlength="2000" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            <div class="char-count"><span id="char-num">0</span> / 2000</div>
        </div>

        <div class="form-group">
            <label>Telefono <span style="font-weight:400;color:#bbb">(opzionale)</span></label>
            <input type="tel" name="phone"
                   placeholder="Es. +39 333 1234567"
                   maxlength="30"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <p class="form-hint">
            📧 La risposta del venditore arriverà alla tua email: <strong><?= htmlspecialchars($me['email']) ?></strong>
        </p>

    </form>

<?php endif; ?>
</div>

<script>
const msgInput = document.getElementById('msg-input');
const charNum  = document.getElementById('char-num');

if (msgInput) {
    msgInput.addEventListener('input', () => {
        charNum.textContent = msgInput.value.length;
    });
    charNum.textContent = msgInput.value.length;
}

const form   = document.getElementById('contact-form');
const btnSend = document.getElementById('btn-send');

if (form) {
    form.addEventListener('submit', function () {
        if (btnSend) {
            btnSend.disabled    = true;
            btnSend.textContent = 'Invio...';
        }
    });
}
</script>
</body>
</html>
