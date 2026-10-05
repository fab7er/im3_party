<?php

$data = include __DIR__ . '/extract.php';

// ---------- Korrekturwerte ----------

if (!defined('TEMP_OFFSET')) {
    define('TEMP_OFFSET', -5.0);      // °C, wird zur gemessenen Temperatur addiert
}
if (!defined('NOISE_OFFSET')) {
    define('NOISE_OFFSET', 107.0);    // dB, wird zum gemessenen (negativen) Lautstaerkewert addiert
}
if (!defined('ALCOHOL_OFFSET')) {
    define('ALCOHOL_OFFSET', -0.80);  // mg/l, wird zum gemessenen Alkoholwert addiert
}

// ---------- Bereinigungs-Funktionen ----------

// Gibt die Zahl als Text zurueck (ohne Umrechnung), oder null wenn es keine Zahl ist.
// Entfernt Leerzeichen und macht aus "18,5" ein "18.5".
if (!function_exists('cleanNumber')) {
    function cleanNumber($value): ?string {
        if ($value === null) {
            return null;
        }
        $value = str_replace(',', '.', trim((string)$value));
        return is_numeric($value) ? $value : null;
    }
}

// Gibt den Zeitstempel zurueck, wenn er dem Format "2026-09-29 14:58:02" entspricht, sonst null.
if (!function_exists('cleanTime')) {
    function cleanTime($value): ?string {
        $value = trim((string)$value);
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return ($d && $d->format('Y-m-d H:i:s') === $value) ? $value : null;
    }
}

// ---------- Zeilen fuer die Datenbank vorbereiten ----------

$rows = [];

// Klima-Boxen (Mensa = 3, Studio = 7)
foreach ([7, 3] as $boxId) {
    $box  = $data[$boxId] ?? [];

    $temp = cleanNumber($box['temperatur']['wert']       ?? null);
    $vol  = cleanNumber($box['lautstaerke']['wert']      ?? null);
    $hum  = cleanNumber($box['luftfeuchtigkeit']['wert'] ?? null);
    $time = cleanTime($box['temperatur']['zeit']         ?? null);  // Zeit der Temperatur gilt fuer die ganze Messung

    // Nur speichern, wenn alles vollstaendig und gueltig ist
    if ($temp !== null && $vol !== null && $hum !== null && $time !== null) {
        $rows[] = [
            'box_id'        => $boxId,
            'measured_at'   => $time,
            'temperature_c' => round((float)$temp + TEMP_OFFSET, 2),  // -5 °C Offset
            'noise_pct'     => round((float)$vol + NOISE_OFFSET, 2),  // +107 auf den (negativen) Wert
            'humidity_pct'  => $hum,
            'alcohol_mgl'   => null,
        ];
    }
}

// Alkohol-Box (5)
$alk     = cleanNumber($data[5]['alkohol']['wert'] ?? null);
$alkTime = cleanTime($data[5]['alkohol']['zeit']   ?? null);

if ($alk !== null && $alkTime !== null) {
    $rows[] = [
        'box_id'        => 5,
        'measured_at'   => $alkTime,
        'temperature_c' => null,
        'noise_pct'     => null,
        'humidity_pct'  => null,
        'alcohol_mgl'   => round((float)$alk + ALCOHOL_OFFSET, 2),  // 0.80 abziehen
    ];
}

// ---------- Ausgabe ----------

// Wenn transform.php direkt im Browser geoeffnet wird: Ergebnis zum Testen anzeigen.
// Wenn load.php sie einbindet: nichts anzeigen.
if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    echo '<pre>' . json_encode($rows, JSON_PRETTY_PRINT) . '</pre>';
}

return $rows;