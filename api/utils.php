<?php
// api/utils.php

function get_env_var($key) {
    // Check $_ENV, $_SERVER, and getenv()
    if (!empty($_ENV[$key])) return $_ENV[$key];
    if (!empty($_SERVER[$key])) return $_SERVER[$key];
    $val = getenv($key);
    return $val !== false ? $val : null;
}

function supabase_request($endpoint, $method = 'GET', $data = null, $headers = []) {
    $url = get_env_var('SUPABASE_URL') . $endpoint;
    $key = get_env_var('SUPABASE_KEY');

    if (!$url || !$key) {
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error: Missing Supabase credentials']);
        exit;
    }

    $default_headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'Prefer: return=representation' // Helper to get data back on insert
    ];

    $all_headers = array_merge($default_headers, $headers);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $all_headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $http_code,
        'body' => json_decode($response, true)
    ];
}

function cors() {
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");         

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }
}
?>
