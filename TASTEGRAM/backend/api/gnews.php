<?php
/**
 * backend/api/gnews.php
 * Proxy sicuro verso GNews API — la chiave non viene mai esposta al browser.
 */

// Impostiamo gli header per il JSON e CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// --- CONFIGURAZIONE ---
define('GNEWS_KEY',  '427f8796ffdcc3964d3dae656ade7019');
define('GNEWS_BASE', 'https://gnews.io/api/v4');

$action = $_GET['action'] ?? 'food_news';

switch ($action) {

    case 'food_news':
        $lang  = in_array($_GET['lang'] ?? 'it', ['it','en']) ? ($_GET['lang'] ?? 'it') : 'it';
        $page  = max(1, (int) ($_GET['page'] ?? 1));

        $url = GNEWS_BASE . '/search?' . http_build_query([
            'q'        => 'cucina OR ricette OR food OR gastronomia',
            'lang'     => $lang,
            'country'  => $lang === 'it' ? 'it' : 'us',
            'max'      => 10,
            'page'     => $page,
            'sortby'   => 'publishedAt',
            'apikey'   => GNEWS_KEY,
        ]);

        $data = gnewsGet($url);
        
        if (!$data) {
            jsonResponse(['success' => false, 'error' => 'Servizio notizie momentaneamente non disponibile'], 503);
        }

        // Se l'API restituisce errori (es. chiave scaduta)
        if (isset($data['errors'])) {
            jsonResponse(['success' => false, 'error' => $data['errors'][0]], 401);
        }

        $articles = array_map(function($a) {
            return [
                'id'          => md5($a['url'] ?? uniqid()),
                'title'       => $a['title'] ?? '',
                'description' => $a['description'] ?? '',
                'url'         => $a['url'] ?? '',
                'image'       => $a['image'] ?? '',
                'publishedAt' => $a['publishedAt'] ?? '',
                'source'      => $a['source']['name'] ?? '',
                'sourceUrl'   => $a['source']['url'] ?? '',
            ];
        }, $data['articles'] ?? []);

        jsonResponse([
            'success'       => true,
            'articles'      => $articles,
            'totalArticles' => $data['totalArticles'] ?? 0,
            'page'          => $page,
        ]);
        break;

    case 'headlines':
        $url = GNEWS_BASE . '/top-headlines?' . http_build_query([
            'topic'   => 'health',
            'lang'    => 'it',
            'country' => 'it',
            'max'     => 6,
            'apikey'  => GNEWS_KEY,
        ]);

        $data = gnewsGet($url);
        
        if (!$data) {
            jsonResponse(['success' => false, 'error' => 'Headline non disponibili'], 503);
        }

        $articles = array_map(function($a) {
            return [
                'title'       => $a['title'] ?? '',
                'description' => $a['description'] ?? '',
                'url'         => $a['url'] ?? '',
                'image'       => $a['image'] ?? '',
                'publishedAt' => $a['publishedAt'] ?? '',
                'source'      => $a['source']['name'] ?? '',
            ];
        }, $data['articles'] ?? []);

        jsonResponse(['success' => true, 'articles' => $articles]);
        break;

    default:
        jsonResponse(['success' => false, 'error' => 'Azione non valida'], 400);
}

// --- FUNZIONI HELPER ---

function gnewsGet(string $url): ?array {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'header'  => "Accept: application/json\r\nUser-Agent: Tastegram/1.0",
        ]
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }

    return json_decode($raw, true);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}