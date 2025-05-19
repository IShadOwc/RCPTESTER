<?php
session_start();
require_once 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Uzupełnij wszystkie pola.";
        header("Location: login.php");
        exit();
    }

    // Przygotowanie zapytania
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    // Sprawdzenie czy użytkownik istnieje
    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userId, $userName, $hashedPassword, $role);
        $stmt->fetch();

        // Weryfikacja hasła
        if (password_verify($password, $hashedPassword)) {
            // Logowanie udane
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $userName;
            $_SESSION['role'] = $role;

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Nieprawidłowe hasło.";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['login_error'] = "Nie znaleziono użytkownika.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>