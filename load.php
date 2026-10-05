<?php
// ===== 1) Zugangsdaten laden =====
require_once __DIR__ . '/config.php';
// Falls die Namen in eurer config.php anders sind, hier anpassen:
$host = $host;
$name = $dbname;
$user = $username;
$pass = $password;

try {
    // ===== 2) Bereinigte Daten holen (transform.php -> extract.php) =====
    $rows = include __DIR__ . '/transform.php';

    // ===== 3) Verbindung zur Datenbank =====
    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ===== 4) SQL-Vorlage =====
    $stmt = $pdo->prepare(
        "INSERT INTO messwerte
            (box_id, measured_at, temperature_c, noise_pct, humidity_pct, alcohol_mgl)
         VALUES
            (:box_id, :measured_at, :temperature_c, :noise_pct, :humidity_pct, :alcohol_mgl)
         ON DUPLICATE KEY UPDATE
            temperature_c = VALUES(temperature_c),
            noise_pct     = VALUES(noise_pct),
            humidity_pct  = VALUES(humidity_pct),
            alcohol_mgl   = VALUES(alcohol_mgl)"
    );

    // ===== 5) Zeilen speichern =====
    foreach ($rows as $row) {
        $stmt->execute($row);
        echo "Box {$row['box_id']} gespeichert ({$row['measured_at']})<br>";
    }

    echo count($rows) . " von 3 Boxen gespeichert.<br>";

} catch (Throwable $e) {
    echo "FEHLER: " . htmlspecialchars($e->getMessage()) . "<br>";
}