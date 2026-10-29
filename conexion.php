<?php
$host = "localhost";
$user = "root";
$password = ""; 
$database = "costos";

try {
    $conexion = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>