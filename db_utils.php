<?php
function load_db() {
    $dbFile = 'db.json';
    if (!file_exists($dbFile)) {
        file_put_contents($dbFile, json_encode([]));
    }
    return json_decode(file_get_contents($dbFile), true);
}

function save_db($data) {
    file_put_contents('db.json', json_encode($data, JSON_PRETTY_PRINT));
}
?>