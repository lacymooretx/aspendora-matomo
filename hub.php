<?php
/**
 * Aspendora ingest endpoint for session-recording chunks and heatmap beacons.
 * Standalone on purpose (no Matomo bootstrap): fast, no session, DB via the same
 * env the container already has. Auth = shared key (ASPENDORA_REC_KEY) — public
 * by design (it ships in page source, like any tracker); caps below bound abuse.
 * Requests are "simple" CORS (no preflight): body is raw JSON, no JSON content type.
 */

header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https://([a-z0-9-]+\.)?(aspendora\.com|aspendoracompliance\.com)$#', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input', false, null, 0, 2_500_000);
$body = json_decode($raw, true);
if (!is_array($body) || ($body['k'] ?? '') === '' || $body['k'] !== getenv('ASPENDORA_REC_KEY')) {
    http_response_code(403);
    exit;
}

$idsite = (int) ($body['idsite'] ?? 0);
$type = $body['t'] ?? '';
$url = substr((string) ($body['url'] ?? ''), 0, 500);
if ($idsite < 1 || $idsite > 1000 || $url === '') {
    http_response_code(400);
    exit;
}

$pdo = new PDO(
    'mysql:host=' . getenv('MATOMO_DATABASE_HOST') . ';dbname=' . getenv('MATOMO_DATABASE_DBNAME') . ';charset=utf8mb4',
    getenv('MATOMO_DATABASE_USERNAME'),
    getenv('MATOMO_DATABASE_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

try {
    if ($type === 'rec') {
        $recKey = preg_replace('/[^a-f0-9]/', '', (string) ($body['key'] ?? ''));
        $events = $body['events'] ?? null;
        if (strlen($recKey) < 8 || !is_array($events) || !$events) {
            http_response_code(400);
            exit;
        }
        // daily cap: keep a runaway client (or abuser) from filling the disk
        $count = $pdo->query('SELECT COUNT(*) FROM matomo_aspendora_recordings WHERE day = CURDATE()')->fetchColumn();
        if ($count > 5000) {
            http_response_code(429);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO matomo_aspendora_recordings
            (idsite, rec_key, seq, day, url, started_at, duration_ms, nb_events, ua, events)
            VALUES (?,?,?,CURDATE(),?,NOW(),?,?,?,?)');
        $stmt->execute([
            $idsite, $recKey, (int) ($body['seq'] ?? 0), $url,
            min((int) ($body['dur'] ?? 0), 86400000), count($events),
            substr((string) ($body['ua'] ?? ''), 0, 300),
            gzcompress(json_encode($events), 6),
        ]);
    } elseif ($type === 'click') {
        $stmt = $pdo->prepare('INSERT INTO matomo_aspendora_heatmap (idsite, day, url, kind, x_pct, y_px, doc_h, vw)
            VALUES (?,CURDATE(),?,\'click\',?,?,?,?)');
        $stmt->execute([
            $idsite, $url,
            max(0, min(100, (float) ($body['x'] ?? 0))),
            max(0, min(500000, (int) ($body['y'] ?? 0))),
            max(0, min(500000, (int) ($body['dh'] ?? 0))),
            max(0, min(20000, (int) ($body['vw'] ?? 0))),
        ]);
    } elseif ($type === 'scroll') {
        $stmt = $pdo->prepare('INSERT INTO matomo_aspendora_heatmap (idsite, day, url, kind, scroll_pct, doc_h, vw)
            VALUES (?,CURDATE(),?,\'scroll\',?,?,?)');
        $stmt->execute([
            $idsite, $url,
            max(0, min(100, (int) ($body['sp'] ?? 0))),
            max(0, min(500000, (int) ($body['dh'] ?? 0))),
            max(0, min(20000, (int) ($body['vw'] ?? 0))),
        ]);
    } else {
        http_response_code(400);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit;
}
http_response_code(204);
