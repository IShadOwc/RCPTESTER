<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8" />
    <title>Logowanie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="styl.css">
</head>
<body>

<div class="login-container">
  <div class="login-left">
    <img src="ETA-logo.jpg" alt="Logo ETA" class="logo">
    <h2>Witaj z powrotem</h2>
    <p class="subtext">Zaloguj się, aby wprowadzić dane do systemu RCP</p>
    <form action="login_handler.php" method="post">
      <input type="text" name="username" placeholder="Wprowadź nazwę użytkownika" required class="LoginFormLabels" autocomplete="off">

      <div class="PasswordContainer">
        <input type="password" id="password" name="password" required class="LoginFormLabels" placeholder="Wprowadź hasło">
        <span class="TogglePassword" onclick="togglePasswordVisibility()">
          <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
            <path d="m644-428-58-58q9-47-27-88t-93-32l-58-58q17-8 34.5-12t37.5-4q75 0 127.5 52.5T660-500q0 20-4 37.5T644-428Zm128 126-58-56q38-29 67.5-63.5T832-500q-50-101-143.5-160.5T480-720q-29 0-57 4t-55 12l-62-62q41-17 84-25.5t90-8.5q151 0 269 83.5T920-500q-23 59-60.5 109.5T772-302Zm20 246L624-222q-35 11-70.5 16.5T480-200q-151 0-269-83.5T40-500q21-53 53-98.5t73-81.5L56-792l56-56 736 736-56 56ZM222-624q-29 26-53 57t-41 67q50 101 143.5 160.5T480-280q20 0 39-2.5t39-5.5l-36-38q-11 3-21 4.5t-21 1.5q-75 0-127.5-52.5T300-500q0-11 1.5-21t4.5-21l-84-82Zm319 93Zm-151 75Z"/>
          </svg>
        </span>
      </div>
      <?php
  if (isset($_SESSION['login_error'])) {
    echo '<p style="color: red; margin-bottom: 15px;">' . $_SESSION['login_error'] . '</p>';
    unset($_SESSION['login_error']);
  }
?>
      <button type="submit" class="login-button">
        <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
          <path d="M480-120v-80h280v-560H480v-80h280q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H480Zm-80-160-55-58 102-102H120v-80h327L345-622l55-58 200 200-200 200Z" fill="white"/>
        </svg>
        Zaloguj się
      </button>


    </form>
  </div>
  <div class="login-right">
    <img src="loading.jpg" alt="Obrazek logowania">
  </div>
</div>

<!-- Skrypty zewnętrzne -->
<script src="main.js"></script>
</body>
</html>
