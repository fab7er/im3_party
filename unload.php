<?php
// unload.php - gibt die Messwerte als JSON aus (ohne Limit)
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // remove if only your own site should access it

// Accepts "2026-09-30" or "2026-09-30 17:00[:00]" (also with a "T" instead of the space)
function parseDateTime(string $value, string $defaultTime): ?string {
    $value = str_replace('T', ' ', trim($value));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $value .= ' ' . $defaultTime;
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return ($d && $d->format('Y-m-d H:i:s') === $value) ? $value : null;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // ---- optional filters via URL ----
    // unload.php?box_id=7&from=2026-09-30 17:00&to=2026-10-01 04:00&order=asc
    $where  = [];
    $params = [];

    if (isset($_GET['box_id']) && ctype_digit($_GET['box_id'])) {
        $where[]           = 'box_id = :box_id';
        $params[':box_id'] = (int)$_GET['box_id'];
    }
    if (!empty($_GET['from']) && ($from = parseDateTime($_GET['from'], '00:00:00'))) {
        $where[]         = 'measured_at >= :from';
        $params[':from'] = $from;
    }
    if (!empty($_GET['to']) && ($to = parseDateTime($_GET['to'], '23:59:59'))) {
        $where[]       = 'measured_at <= :to';
        $params[':to'] = $to;
    }
    // exclude boxes: exclude_box=5 or exclude_box=5,3
    if (!empty($_GET['exclude_box'])) {
        $ids = array_filter(
            explode(',', $_GET['exclude_box']),
            fn($v) => ctype_digit(trim($v))
        );
        foreach (array_values($ids) as $i => $id) {
            $where[]            = "box_id <> :ex$i";
            $params[":ex$i"]    = (int)trim($id);
        }
    }

    $order = (($_GET['order'] ?? '') === 'asc') ? 'ASC' : 'DESC';

    $sql = "SELECT box_id, measured_at, temperature_c, noise_pct, humidity_pct, alcohol_mgl
            FROM messwerte"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY measured_at $order";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // MySQL returns strings; convert to proper numbers (NULL stays null)
    foreach ($rows as &$r) {
        $r['box_id'] = (int)$r['box_id'];
        foreach (['temperature_c', 'noise_pct', 'humidity_pct', 'alcohol_mgl'] as $k) {
            $r[$k] = $r[$k] !== null ? (float)$r[$k] : null;
        }
    }
    unset($r);

    echo json_encode(
        ['count' => count($rows), 'data' => $rows],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankfehler']);
    // error_log($e->getMessage()); // log details instead of exposing them
}