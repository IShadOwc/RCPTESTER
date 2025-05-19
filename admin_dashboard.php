<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "Brak dostępu. Tylko administrator może przeglądać tę stronę.";
    exit;
}

include('db.php'); // Connect to the database

// Block cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
// Handle adding a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $email = mysqli_real_escape_string($conn, $_POST['email']); // Nowy: pobieramy email

    $query = "INSERT INTO users (username, password, role, email, created_at) 
              VALUES ('$username', '$password', '$role', '$email', NOW())";
    
    if (mysqli_query($conn, $query)) {
        $record_id = mysqli_insert_id($conn);
        $action = "Dodano użytkownika: $username";
        $table_name = "users";
        $details = json_encode(['username' => $username, 'role' => $role, 'email' => $email]);

        mysqli_query($conn, "INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) 
                             VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$action', '$table_name', '$record_id', '$details', NOW())");
        echo "<script>alert('Użytkownik został dodany pomyślnie!');</script>";
    } else {
        echo "<script>alert('Błąd podczas dodawania użytkownika: " . mysqli_error($conn) . "');</script>";
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?section=manage_user");
    exit;
}


// Handle deleting a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);

        // Prevent deleting the currently logged-in user
        if ($user_id == $_SESSION['user_id']) {
            echo "<script>alert('Nie możesz usunąć swojego konta!');</script>";
        } else {
            // Fetch user details before deletion for logging
            $user_query = "SELECT username, role FROM users WHERE id = $user_id";
            $user_result = mysqli_query($conn, $user_query);
            $user_details = mysqli_fetch_assoc($user_result);

            if ($user_details) {
                $delete_query = "DELETE FROM users WHERE id = $user_id";
                if (mysqli_query($conn, $delete_query)) {
                    $action = "Usunięto użytkownika o ID: $user_id";
                    $details = json_encode([
                        'username' => $user_details['username'],
                        'role' => $user_details['role']
                    ]);

                    mysqli_query($conn, "INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$action', 'users', '$user_id', '$details', NOW())");
                    echo "<script>alert('Użytkownik został usunięty pomyślnie!');</script>";
                } else {
                    echo "<script>alert('Błąd podczas usuwania użytkownika: " . mysqli_error($conn) . "');</script>";
                }
            } else {
                echo "<script>alert('Nie znaleziono użytkownika do usunięcia.');</script>";
            }
        }
    } else {
        echo "<script>alert('Nie wybrano użytkownika do usunięcia.');</script>";
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle updating a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $new_username = isset($_POST['new_username']) && $_POST['new_username'] !== '' ? mysqli_real_escape_string($conn, $_POST['new_username']) : null;
    $new_password = !empty($_POST['new_password']) ? password_hash($_POST['new_password'], PASSWORD_BCRYPT) : null;
    $new_role = isset($_POST['new_role']) && $_POST['new_role'] !== '' ? mysqli_real_escape_string($conn, $_POST['new_role']) : null;
    $new_email = isset($_POST['new_email']) && $_POST['new_email'] !== '' ? mysqli_real_escape_string($conn, $_POST['new_email']) : null;
    $max_hours_per_day = isset($_POST['max_hours_per_day']) ? (int)$_POST['max_hours_per_day'] : null;

    $sql = "UPDATE users SET ";

    $updates = [];
    if ($new_username) $updates[] = "username = '$new_username'";
    if ($new_password) $updates[] = "password = '$new_password'";
    if ($new_role) $updates[] = "role = '$new_role'";
    if ($new_email) $updates[] = "email = '$new_email'";
    if ($max_hours_per_day !== null) $updates[] = "max_hours_per_day = '$max_hours_per_day'";

    if (count($updates) > 0) {
        $sql .= implode(", ", $updates);
        $sql .= " WHERE id = '$user_id'";

        if (mysqli_query($conn, $sql)) {
            echo "Użytkownik został zaktualizowany.";
        } else {
            echo "Błąd podczas aktualizacji użytkownika: " . mysqli_error($conn);
        }
    }
}



// Handle adding a project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $code = mysqli_real_escape_string($conn, $_POST['code']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $stmt = $conn->prepare("INSERT INTO projects (name, code, description, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $name, $code, $description);
    if ($stmt->execute()) {
        $project_id = $stmt->insert_id; // Get the ID of the newly inserted project
        $action = "Dodano projekt: $name";
        $details = json_encode(['name' => $name, 'code' => $code, 'description' => $description]);

        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) VALUES (?, ?, ?, 'projects', ?, ?, NOW())");
        $log_stmt->bind_param("issis", $_SESSION['user_id'], $_SESSION['username'], $action, $project_id, $details);
        $log_stmt->execute();

        echo "<script>alert('Projekt został dodany pomyślnie!');</script>";
    } else {
        echo "<script>alert('Błąd podczas dodawania projektu: " . $stmt->error . "');</script>";
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?section=manage_project");
    exit;
}
// Handle deleting a project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    if (isset($_POST['project_id']) && !empty($_POST['project_id'])) {
        $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);

        // Fetch project details before deletion for logging
        $project_query = "SELECT name, code, description FROM projects WHERE id = $project_id";
        $project_result = mysqli_query($conn, $project_query);
        $project_details = mysqli_fetch_assoc($project_result);

        if ($project_details) {
            $delete_query = "DELETE FROM projects WHERE id = $project_id";
            if (mysqli_query($conn, $delete_query)) {
                $action = "Usunięto projekt o ID: $project_id";
                $details = json_encode($project_details);

                mysqli_query($conn, "INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$action', 'projects', '$project_id', '$details', NOW())");
                echo "<script>alert('Projekt został usunięty pomyślnie!');</script>";
            } else {
                echo "<script>alert('Błąd podczas usuwania projektu: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            echo "<script>alert('Nie znaleziono projektu do usunięcia.');</script>";
        }
    } else {
        echo "<script>alert('Nie wybrano projektu do usunięcia.');</script>";
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?section=manage_project");
    exit;
}

// Handle updating a project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    $new_name = mysqli_real_escape_string($conn, $_POST['new_name']);
    $new_code = mysqli_real_escape_string($conn, $_POST['new_code']);
    $new_description = isset($_POST['new_description']) && $_POST['new_description'] !== '' ? mysqli_real_escape_string($conn, $_POST['new_description']) : null;

    // Fetch current project details for logging
    $project_query = "SELECT name, code, description FROM projects WHERE id = $project_id";
    $project_result = mysqli_query($conn, $project_query);
    $current_project_details = mysqli_fetch_assoc($project_result);

    if ($current_project_details) {
        $update_query = "UPDATE projects SET ";
        $updates = [];
        if (!empty($new_name)) $updates[] = "name = '$new_name'";
        if (!empty($new_code)) $updates[] = "code = '$new_code'";
        if ($new_description !== null) {
            $updates[] = "description = " . ($new_description === '' ? "NULL" : "'$new_description'");
        }
        if (!empty($updates)) {
            $update_query .= implode(", ", $updates) . " WHERE id = $project_id";

            if (mysqli_query($conn, $update_query)) {
                $action = "Zaktualizowano projekt o ID: $project_id";
                $details = json_encode([
                    'previous' => [
                        'name' => $current_project_details['name'],
                        'code' => $current_project_details['code'],
                        'description' => $current_project_details['description']
                    ],
                    'updated_to' => [
                        'name' => $new_name ?? $current_project_details['name'],
                        'code' => $new_code ?? $current_project_details['code'],
                        'description' => $new_description ?? $current_project_details['description']
                    ]
                ]);

                mysqli_query($conn, "INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) VALUES ('{$_SESSION['user_id']}', '{$_SESSION['username']}', '$action', 'projects', '$project_id', '$details', NOW())");
                echo "<script>alert('Projekt został zaktualizowany pomyślnie!');</script>";
            } else {
                echo "<script>alert('Błąd podczas aktualizacji projektu: " . mysqli_error($conn) . "');</script>";
            }
        } else {
            echo "<script>alert('Brak zmian do zapisania.');</script>";
        }
    } else {
        echo "<script>alert('Nie znaleziono projektu do zaktualizowania.');</script>";
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?section=manage_project");
    exit;
}

// Endpoint do zwracania danych użytkownika jako JSON
if (isset($_GET['get_user_data']) && isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $result = mysqli_query($conn, "SELECT username, role, email, max_hours_per_day FROM users WHERE id = $user_id");
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Użytkownik nie znaleziony']);
    }
    exit;
}



// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get all projects from the database
$query = "SELECT * FROM projects";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administratora</title>
    <link rel="stylesheet" href="styl.css">
</head>
<body>
<nav class="navbar">
        <div class="nav-left">
        <a href="index.php" class="logo"><img src="ETA-logo.jpg" alt="Logo"></a>
        </div>
        <div class="nav-right">
        <a href="index.php" class="RCP">RCP</a>
        <p class="navbar-user">
            <?php echo htmlspecialchars($_SESSION['username']); ?>
        </p>


            <?php
            // Sprawdzamy, czy użytkownik ma rolę 'admin'
            if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
            // Jeśli rola to 'admin', wyświetlamy link do strony administratora
            echo '<a href="admin_dashboard.php">Administrator</a>';
            }
            ?>
            <button id="theme-toggle" onclick="toggleTheme()" style="background: none; border: none; padding: 0; cursor: pointer;">
                <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                    <path id="theme-path" d="M480-360q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35Zm0 80q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Zm326-268Z"/>
                </svg>
            </button>
        <script>
// Funkcja do ustawienia motywu
function setTheme(theme) {
    const body = document.body;
    const themePath = document.getElementById('theme-path');

    if (theme === 'dark') {
        body.classList.add('dark-theme');
        themePath.setAttribute('d', 'M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Zm0-80q88 0 158-48.5T740-375q-20 5-40 8t-40 3q-123 0-209.5-86.5T364-660q0-20 3-40t8-40q-78 32-126.5 102T200-480q0 116 82 198t198 82Zm-10-270Z');
        localStorage.setItem('theme', 'dark');
        setCookie('theme', 'dark', 30);
    } else {
        body.classList.remove('dark-theme');
        themePath.setAttribute('d', 'M480-360q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35Zm0 80q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Zm326-268Z');
        localStorage.setItem('theme', 'light');
        setCookie('theme', 'light', 30);
    }
}

// Funkcja do przełączania motywu
function toggleTheme() {
    const html = document.documentElement;  // Wybieramy element <html>
    const currentTheme = html.getAttribute('data-theme'); // Pobieramy aktualny motyw
    
    // Przełączamy między motywami (jeśli jest "dark", ustawiamy "light", w przeciwnym razie "dark")
    if (currentTheme === 'dark') {
        html.setAttribute('data-theme', 'light');  // Zmiana na motyw jasny
    } else {
        html.setAttribute('data-theme', 'dark');   // Zmiana na motyw ciemny
    }
    
    // Opcjonalnie: Zapisujemy wybrany motyw w localStorage, żeby był zachowany po odświeżeniu strony
    localStorage.setItem('theme', html.getAttribute('data-theme'));
}

// Przy ładowaniu strony sprawdzamy, jaki motyw był zapisany w localStorage
window.onload = function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
};

</script>
        <button id="font-toggle" onclick="toggleFontSize()" style="background: none; border: none; padding: 0; cursor: pointer;">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                <path d="M660-240v-248l-64 64-56-56 160-160 160 160-56 56-64-64v248h-80Zm-540 0 165-440h79l165 440h-76l-39-113H236l-40 113h-76Zm139-177h131l-65-182h-4l-62 182Z"/>
            </svg>
        </button>
        <script>
            let fontSizeState = 2; // 1 = small, 2 = normal, 3 = large

            function setCookie(name, value, days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = name + "=" + value + ";" + expires + ";path=/";
            }

            function getCookie(name) {
            const nameEQ = name + "=";
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                let cookie = cookies[i].trim();
                if (cookie.indexOf(nameEQ) === 0) {
                return cookie.substring(nameEQ.length, cookie.length);
                }
            }
            return null;
            }

            function toggleFontSize() {
            const body = document.body;

            // Remove all font size classes
            body.classList.remove('font-small', 'font-normal', 'font-large');

            // Cycle through font sizes
            fontSizeState = fontSizeState === 3 ? 1 : fontSizeState + 1;

            // Apply the new font size class
            if (fontSizeState === 1) {
                body.classList.add('font-small');
            } else if (fontSizeState === 2) {
                body.classList.add('font-normal');
            } else if (fontSizeState === 3) {
                body.classList.add('font-large');
            }

            // Save the font size state in a cookie
            setCookie('fontSizeState', fontSizeState, 30); // Save for 30 days
            }

            // Apply saved font size on page load
            document.addEventListener('DOMContentLoaded', () => {
            const savedFontSizeState = getCookie('fontSizeState');
            if (savedFontSizeState) {
                fontSizeState = parseInt(savedFontSizeState, 10);

                // Apply the saved font size class
                const body = document.body;
                body.classList.remove('font-small', 'font-normal', 'font-large');
                if (fontSizeState === 1) {
                body.classList.add('font-small');
                } else if (fontSizeState === 2) {
                body.classList.add('font-normal');
                } else if (fontSizeState === 3) {
                body.classList.add('font-large');
                }
            }
            });
        </script>
        </div>
    </nav>
    <!-- Sidebar button-->
    <button id="toggle-sidebar" aria-label="Toggle Sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000">
    <path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>
  </svg>
</button>
<div id="sidebar-overlay"></div>

    <div class="container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php">RCP</a></li>
                <li><a href="kalendarz.php">Kalendarz</a></li>
                <?php
            // Sprawdzamy, czy użytkownik ma rolę 'admin'
            if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
            // Jeśli rola to 'admin', wyświetlamy link do strony administratora
            echo '<a href="workers_hours.php">Godziny pracowników</a>';
            }
            ?>
            </ul>
            </ul>
            <a href="?logout=true" class="logout-btn">Wyloguj</a>
        </div>

        <div class="container-admin">
            <h1 class="AdminH1">Panel Administratora</h1>
            <ul class="admin-options-horizontal">
                <li onclick="showContent('users')">Zarządzaj uzytkownikami</li>
                <li onclick="showContent('projects')">Zarządzaj projektami</li>
                <li onclick="showContent('logs')">Pokaż logi</li>
            </ul>
            <div id="content-container">
                <div id="projects-content" class="content-section" style="display: none;">
                    <ul class="admin-options-horizontal">
                        <li onclick="handleProjectAction('add_project')">Dodaj projekt</li>
                        <li onclick="handleProjectAction('manage_project')">Zarządzaj projektami</li>
                        <li onclick="handleProjectAction('list_project')">Pokaż listę projektów</li>
                    </ul>
                    <div id="project-action-container"></div>
                </div>

                <div id="users-content" class="content-section" style="display: none;">
                    <ul class="admin-options-horizontal">
                        <li onclick="handleUserAction('add_user')">Dodaj użytkownika</li>
                        <li onclick="handleUserAction('manage_user')">Zarządzaj użytkownikami</li>
                        <li onclick="handleUserAction('list_user')">Pokaż listę użytkowników</li>
                    </ul>
                    <div id="user-action-container"></div>
                </div>

                <div id="logs-content" class="content-section" style="display: none;">
                    <table class="log-list-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ID użytkownika</th>
                                <th>Nazwa użytkownika</th>
                                <th>Akcja</th>
                                <th>Nazwa tabeli</th>
                                <th>ID rekordu</th>
                                <th>Szczegóły</th>
                                <th>Znacznik czasu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT id, user_id, username, action, table_name, record_id, details, timestamp FROM logs ORDER BY timestamp DESC";
                            $result = mysqli_query($conn, $query);
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>
                                    <td data-label='ID'>{$row['id']}</td>
                                    <td data-label='ID użytkownika'>{$row['user_id']}</td>
                                    <td data-label='Nazwa użytkownika'>{$row['username']}</td>
                                    <td data-label='Akcja'>{$row['action']}</td>
                                    <td data-label='Nazwa tabeli'>{$row['table_name']}</td>
                                    <td data-label='ID rekordu'>{$row['record_id']}</td>
                                    <td data-label='Szczegóły'>{$row['details']}</td>
                                    <td data-label='Znacznik czasu'>{$row['timestamp']}</td>
                                </tr>";
                                }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
            const usersData = <?php
        $query = "SELECT id, username, email, max_hours_per_day, role FROM users";
        $result = mysqli_query($conn, $query);
        $users = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
        echo json_encode($users);
    ?>;

    const projectsData = <?php
        $query = "SELECT id, name, code, description FROM projects";
        $result = mysqli_query($conn, $query);
        $projects = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $projects[] = $row;
        }
        echo json_encode($projects);
    ?>;
        function showContent(section) {
            const sections = document.querySelectorAll('.content-section');
            sections.forEach(sec => sec.style.display = 'none');
            const selectedSection = document.getElementById(`${section}-content`);
            if (selectedSection) {
                selectedSection.style.display = 'block';
            }
        }

        // Automatically show the correct section based on the "section" query parameter
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section');
            if (section) {
                showContent(section);
            }
        });

        function handleUserAction(action) {
            const container = document.getElementById('user-action-container');
            container.innerHTML = '';

            switch (action) {
                case 'add_user':
                    container.innerHTML = `
                        <form method="post">
                            <label for="username">Nazwa użytkownika:</label>
                            <input type="text" id="username" name="username" required><br>
                            <label for="password">Hasło:</label>
                            <input type="password" id="password" name="password" required><br>
                            <label for="role">Rola:</label>
                            <select id="role" name="role">
                                <option value="user">Użytkownik</option>
                                <option value="admin">Administrator</option>
                            </select><br>
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required><br>
                            <label for="max_hours_per_day">Maksymalne godziny dziennie:</label>
                            <input type="number" id="max_hours_per_day" name="max_hours_per_day" min="0" max="8" step="0.5" value="8"><br>
                            <button type="submit" name="add_user">Dodaj użytkownika</button>

                        </form>
                    `;
                    break;
                    case 'manage_user':
    container.innerHTML = `
        <form method="post">
            <label for="user_id">Wybierz użytkownika:</label>
            <select id="user_id" name="user_id" required></select><br>
            <label for="new_username">Nowa nazwa użytkownika:</label>
            <input type="text" id="new_username" name="new_username"><br>
            <label for="new_password">Nowe hasło:</label>
            <input type="password" id="new_password" name="new_password"><br>
            <label for="new_role">Nowa rola:</label>
            <select id="new_role" name="new_role">
                <option value="">Nie zmieniaj</option>
                <option value="user">Użytkownik</option>
                <option value="admin">Administrator</option>
            </select><br>
            <label for="new_email">Nowy email:</label>
            <input type="email" id="new_email" name="new_email"><br>
            <label for="max_hours_per_day">Maksymalne godziny dziennie:</label>
            <input type="number" id="max_hours_per_day" name="max_hours_per_day" min="0" max="8" step="0.5"><br>
            <div style="margin-top: 10px;">
                <button type="submit" name="update_user">Zaktualizuj użytkownika</button><br>
                <button type="submit" name="delete_user" style="background-color:rgb(255, 0, 0); color: white;">Usuń użytkownika</button>
            </div>
        </form>
    `;

    const userSelect = document.getElementById('user_id');
    usersData.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.username;
        userSelect.appendChild(option);
    });

    userSelect.addEventListener('change', () => {
        const selected = usersData.find(u => u.id == userSelect.value);
        if (selected) {
            document.getElementById('new_username').value = selected.username;
            document.getElementById('new_email').value = selected.email;
            document.getElementById('max_hours_per_day').value = selected.max_hours_per_day;
            document.getElementById('new_role').value = selected.role;
        }
    });

    // Wywołaj raz dla pierwszego użytkownika (jeśli jest)
    if (usersData.length > 0) {
        userSelect.value = usersData[0].id;
        userSelect.dispatchEvent(new Event('change'));
    }


                    break;
                case 'list_user':
                    container.innerHTML = `<table class="user-list-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nazwa użytkownika</th>
                                <th>Email</th>
                                <th>maks. godziny dziennie</th>
                                <th>Rola</th>
                                <th>Data utworzenia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT id, username, email, max_hours_per_day, role, created_at FROM users";
                            $result = mysqli_query($conn, $query);
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>
                                    <td>{$row['id']}</td>
                                    <td>{$row['username']}</td>
                                    <td>{$row['email']}</td>
                                    <td>{$row['max_hours_per_day']}</td>
                                    <td>{$row['role']}</td>
                                    <td>{$row['created_at']}</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>`;
                    break;
                default:
                    console.error('Unknown action:', action);
            }
        }

        function handleProjectAction(action) {
            const container = document.getElementById('project-action-container');
            container.innerHTML = ''; // Clear the container before adding new content

            switch (action) {
                case 'add_project':
                    container.innerHTML = `
                        <form method="post">
                            <label for="name">Nazwa projektu:</label>
                            <input type="text" id="name" name="name" required><br>
                            <label for="code">Zlecenie:</label>
                            <input type="text" id="code" name="code"><br>
                            <label for="description">Opis projektu:</label>
                            <textarea id="description" name="description"></textarea><br>
                            <button type="submit" name="add_project">Dodaj projekt</button>
                        </form>
                    `;
                    break;
                    case 'manage_project':
    container.innerHTML = `
        <form method="post">
            <label for="project_id">Wybierz projekt:</label>
            <select id="project_id" name="project_id" required></select><br>
            <label for="new_name">Nowa nazwa projektu:</label>
            <input type="text" id="new_name" name="new_name"><br>
            <label for="new_code">Nowe zlecenie:</label>
            <input type="text" id="new_code" name="new_code"><br>
            <label for="new_description">Nowy opis projektu:</label>
            <textarea id="new_description" name="new_description"></textarea><br>
            <div style="margin-top: 10px;">
                <button type="submit" name="update_project">Zaktualizuj projekt</button><br>
                <button type="submit" name="delete_project" style="background-color: #ff4d4d; color: white;">Usuń projekt</button>
            </div>
        </form>
    `;

    const projectSelect = document.getElementById('project_id');
    projectsData.forEach(proj => {
        const option = document.createElement('option');
        option.value = proj.id;
        option.textContent = proj.name;
        projectSelect.appendChild(option);
    });

    projectSelect.addEventListener('change', () => {
        const selected = projectsData.find(p => p.id == projectSelect.value);
        if (selected) {
            document.getElementById('new_name').value = selected.name;
            document.getElementById('new_code').value = selected.code;
            document.getElementById('new_description').value = selected.description;
        }
    });

    if (projectsData.length > 0) {
        projectSelect.value = projectsData[0].id;
        projectSelect.dispatchEvent(new Event('change'));
    }
    break;

                case 'list_project':
                    container.innerHTML = `<table class="project-list-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nazwa</th>
                                <th>Zlecenie</th>
                                <th>Opis</th>
                                <th>Data utworzenia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT id, name, code, description, created_at FROM projects";
                            $result = mysqli_query($conn, $query);
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>
                                    <td>{$row['id']}</td>
                                    <td>{$row['name']}</td>
                                    <td>{$row['code']}</td>
                                    <td>{$row['description']}</td>
                                    <td>{$row['created_at']}</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>`;
                    break;
                default:
                    console.error('Unknown action:', action);
            }
        }
    </script>
            </div>
        </div>
    </div>
    <script>
document.addEventListener("DOMContentLoaded", () => {
  const toggleSidebarBtn = document.getElementById('toggle-sidebar');
  const sidebar = document.querySelector('.sidebar');
  const sidebarOverlay = document.getElementById('sidebar-overlay');

  toggleSidebarBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
    sidebarOverlay.classList.toggle('active');

    // Obsługa przesunięcia przycisku tylko na PC/Tablet
    if (window.innerWidth > 767) {
      toggleSidebarBtn.style.left = sidebar.classList.contains('active') ? '230px' : '10px';
    }
  });

  sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('active');
    sidebarOverlay.classList.remove('active');

    if (window.innerWidth > 767) {
      toggleSidebarBtn.style.left = '10px';
    }
  });
});

</script>
</body>
</html>
