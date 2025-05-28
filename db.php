<?php
// db.php
$host = "dpg-d0rhkuali9vc738kqh20-a";      // Ganti dengan host PostgreSQL dari Render
$port = 5432;
$dbname = "drm_db";                 // Nama database
$user = "drm_db_user";                // Username
$password = "9LziZvxV6qsXIwwY6JDTCO0ZtzqGYXHU";         // Password

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
