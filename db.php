<?php
$servername = "localhost";
$username = "root";
$password = ""; // Jeśli nie ustawiłeś hasła w XAMPP
$database = "rcp1"; // <- ZMIEŃ na nazwę swojej bazy danych

$conn = new mysqli($servername, $username, $password, $database);

// Sprawdź połączenie
if ($conn->connect_error) {
    die("Połączenie nieudane: " . $conn->connect_error);
}
?>
