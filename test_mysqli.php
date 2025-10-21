<?php
// test_mysqli.php
// Tes koneksi mysqli sederhana — jalankan: php test_mysqli.php

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'invitation';

$mysqli = @mysqli_connect($host, $user, $pass, $db);

if ($mysqli && !mysqli_connect_errno()) {
    echo "MYSQLI OK\n";
    mysqli_close($mysqli);
    exit(0);
} else {
    echo "MYSQLI FAILED: " . mysqli_connect_error() . "\n";
    exit(1);
}
