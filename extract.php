<?php

if (!function_exists('fetchJson')) {
    function fetchJson(string $url): ?array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string)$response, true);
        return is_array($data) ? $data : null;
    }
}

$base = 'https://sensorbox.fiessling.ch/api/get.php';

return [
    // Sensor-Box 7
    7 => [
        'temperatur'       => fetchJson("$base?boxid=7&sensor=temperatur"),
        'lautstaerke'      => fetchJson("$base?boxid=7&sensor=lautstaerke"),
        'luftfeuchtigkeit' => fetchJson("$base?boxid=7&sensor=luftfeuchtigkeit"),
    ],
    // Sensor-Box 3
    3 => [
        'temperatur'       => fetchJson("$base?boxid=3&sensor=temperatur"),
        'lautstaerke'      => fetchJson("$base?boxid=3&sensor=lautstaerke"),
        'luftfeuchtigkeit' => fetchJson("$base?boxid=3&sensor=luftfeuchtigkeit"),
    ],
    // Alkohol Box 5
    5 => [
        'alkohol'          => fetchJson("$base?boxid=5&sensor=alkohol"),
    ],
];