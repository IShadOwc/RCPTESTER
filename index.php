<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include('db.php');

// Debug, checking session variables
// echo '<pre>';
// print_r($_SESSION);
// echo '</pre>';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit-hours'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username']; // Assuming username is stored in the session
    $project_id = $_POST['project'];
    $hours = str_replace(',', '.', $_POST['hours']); // Replace commas with dots
    $date = $_POST['date'];
    $created_at = date('Y-m-d H:i:s'); // Current timestamp

    // Validate inputs
    if (empty($project_id) || empty($hours) || empty($date) || $hours < 0 || $hours > 8 || fmod($hours, 0.5) !== 0.0) {
        echo "<script>alert('Wprowadź poprawną liczbę godzin (od 0 do 8, z krokiem 0.5).');</script>";
    } else {
        // check if the user has already entered hours for the same date
        $check_query = "SELECT SUM(hours) as total_hours FROM work_entries WHERE user_id = ? AND date = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param('is', $user_id, $date);
        $check_stmt->execute();
        $check_stmt->store_result();
        $check_stmt->bind_result($total_hours);
        $check_stmt->fetch();

        // If the user has already entered hours for the same date, check if the new entry exceeds 8 hours
        if ($total_hours + $hours > 8) {
            echo "<script>alert('Nie możesz wprowadzić więcej niż 8 godzin pracy na ten dzień.');</script>";
        } else {
            // Fetch project name from the projects table
            $project_query = "SELECT name FROM projects WHERE id = ?";
            $project_stmt = $conn->prepare($project_query);
            $project_stmt->bind_param('i', $project_id);
            $project_stmt->execute();
            $project_result = $project_stmt->get_result();

            if ($project_result->num_rows > 0) {
                $project_row = $project_result->fetch_assoc();
                $project_name = $project_row['name'];

                // Insert work entry into the database
                $query = "INSERT INTO work_entries (user_id, username, project_id, project_name, date, hours, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('isissds', $user_id, $username, $project_id, $project_name, $date, $hours, $created_at);

                if ($stmt->execute()) {
                    // Get the last inserted ID for the work entry
                    $record_id = $stmt->insert_id;

                    // Insert log entry into the logs table
                    $action = "Wprowadzono godziny pracy";
                    $table_name = "work_entries";
                    $details = "Projekt: $project_name, Data: $date, Godziny: $hours";
                    $log_query = "INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $log_stmt = $conn->prepare($log_query);
                    $log_stmt->bind_param('isssiss', $user_id, $username, $action, $table_name, $record_id, $details, $created_at);
                    $log_stmt->execute();

                    // Redirect to avoid form resubmission
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    echo "<script>alert('Błąd: " . $stmt->error . "');</script>";
                }
            } else {
                echo "<script>alert('Wybrany projekt nie istnieje.');</script>";
            }
        }
    }
}

function isHolidayOrWeekend($date) {
    $holidays = [
        // 2025
        '2025-01-01', '2025-01-06', '2025-04-20', '2025-04-21', '2025-05-01', '2025-05-03',
        '2025-06-08', '2025-06-19', '2025-08-15', '2025-11-01', '2025-11-11', '2025-12-25', '2025-12-26',
        // 2026
        '2026-01-01', '2026-01-06', '2026-04-05', '2026-04-06', '2026-05-01', '2026-05-03',
        '2026-05-24', '2026-06-04', '2026-08-15', '2026-11-01', '2026-11-11', '2026-12-25', '2026-12-26',
        // 2027
        '2027-01-01', '2027-01-06', '2027-03-28', '2027-03-29', '2027-05-01', '2027-05-03',
        '2027-05-16', '2027-05-27', '2027-08-15', '2027-11-01', '2027-11-11', '2027-12-25', '2027-12-26'
    ];

    $timestamp = strtotime($date);
    $dayOfWeek = date('w', $timestamp); // 0 = niedziela, 6 = sobota

    if ($dayOfWeek == 0 || $dayOfWeek == 6 || in_array($date, $holidays)) {
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date'])) {
    if (isHolidayOrWeekend($_POST['date'])) {
        echo "<script>alert('Nie można wprowadzać godzin w weekendy ani w dni świąteczne!'); window.history.back();</script>";
        exit;
    }
}



            // Delete hours record in work_entries table
            if (isset($_POST['delete']) && isset($_POST['record_id'])) {
            $record_id = $_POST['record_id'];

            // check if the record exists and belongs to the logged-in user
            $check_query = "SELECT user_id, project_name, hours, date FROM work_entries WHERE id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param('i', $record_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
            $check_row = $check_result->fetch_assoc();

            // If the record belongs to the logged-in user, proceed with deletion
            if ($check_row['user_id'] == $_SESSION['user_id']) {
            $project_name = $check_row['project_name'];
            $hours = $check_row['hours'];
            $date = $check_row['date'];
            $user_id = $_SESSION['user_id'];
            $username = $_SESSION['username'];
            $created_at = date('Y-m-d H:i:s');

            // Delete the record from work_entries table
            $delete_query = "DELETE FROM work_entries WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param('i', $record_id);

            if ($delete_stmt->execute()) {
                // Zapisujemy log do bazy
                $action = "Usunięto godziny pracy";
                $table_name = "work_entries";
                $details = "Projekt: $project_name, Data: $date, Godziny: $hours";

                $log_query = "INSERT INTO logs (user_id, username, action, table_name, record_id, details, timestamp) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param('isssiss', $user_id, $username, $action, $table_name, $record_id, $details, $created_at);
                $log_stmt->execute();

                echo "<script>alert('Godziny zostały usunięte.'); window.location.href='index.php';</script>";
            } else {
                echo "<script>alert('Błąd: " . $delete_stmt->error . "');</script>";
            }
        } else {
            echo "<script>alert('Nie możesz usunąć godzin innych użytkowników.'); window.location.href='index.php';</script>";
        }
    } else {
        echo "<script>alert('Rekord nie istnieje.'); window.location.href='index.php';</script>";
    }
}


$user_id = $_SESSION['user_id'];
$date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d'); // Downloaded date from form or default to today

$query = "SELECT SUM(hours) AS total_hours FROM work_entries WHERE user_id = ? AND date = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('is', $user_id, $date);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$hours_taken = $row['total_hours'] ?? 0;
$max_hours = 8; // Maximum hours allowed per day

// If the user has already entered hours for the selected date, set the date to today
if ($hours_taken >= $max_hours) {
    $date = date('Y-m-d'); // Set date to today if max hours reached
}



        // block browsing cache
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        // If the user is not logged in, redirect to login page
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }

        if (isset($_GET['logout'])) {
            session_unset();
            session_destroy();
            header("Location: login.php");
            exit;
        }
?>


<!DOCTYPE html>
<html lang="pl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Czasu Pracy</title>
    <link rel="stylesheet" href="styl.css"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        <button id="font-toggle" onclick="toggleFontSize()" style="background: none; border: none; padding: 0; cursor: pointer;">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#1f1f1f">
                <path d="M660-240v-248l-64 64-56-56 160-160 160 160-56 56-64-64v248h-80Zm-540 0 165-440h79l165 440h-76l-39-113H236l-40 113h-76Zm139-177h131l-65-182h-4l-62 182Z"/>
            </svg>
        </button>

        </div>
        </div>
    </nav>
<!-- Sidebar button-->
    <button id="toggle-sidebar" aria-label="Toggle Sidebar">
  <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
    <path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/>
  </svg>
</button>
<div id="sidebar-overlay"></div>

<div class="container">
    <div class="sidebar">
        <div class="menu-icon-wrapper">
            <a href="#" class="menu-icon"></a>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php">RCP</a></li>
            <li><a href="kalendarz.php">Kalendarz</a></li>
            <?php
            // Check if the user has the 'admin' role
            if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
            echo '<li><a href="workers_hours.php">Godziny pracowników</a></li>';
            }
            ?>
        </ul>
        <a href="?logout=true" class="logout-btn">Wyloguj</a>
    </div>
</div>

        <div class="main">
            <div class="block-large">
                <h2>Wprowadź czas pracy</h2>
                <form action="index.php" method="post" class="form">
                <label for="date">Data:</label>
                <!-- Set default date to last work date or today using cookie -->
                <?php
                    $last_date = isset($_COOKIE['last_work_date']) ? $_COOKIE['last_work_date'] : date('Y-m-d');
                ?>
                <input type="date" id="date" name="date" value="<?php echo $last_date; ?>" max="<?php echo date('Y-m-d'); ?>" required>

                <label for="hours">Liczba godzin:</label>
                <input type="number" id="hours" name="hours" min="0" max="8" step="0.5" required><br>

                    <label for="project">Projekt:</label>
                    <select id="project" name="project" required>
                        <option value="" disabled selected>Wybierz projekt</option>
                    <?php
                    include('db.php');
                    $result = $conn->query("SELECT id, name FROM projects ORDER BY name ASC");

                    if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                    echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
                    }
                    } else {
                        echo '<option disabled>Brak projektów</option>';
                    }
                    ?>
                    </select><br>

                    <div style="text-align: center; margin-top: 10px;">
                        <input type="submit" name="submit-hours" value="Zatwierdź" class="submit-btn">
                    </div>
                </form>
                <form action="index.php" method="post" class="form">
                    <h3>Twoje zapisane godziny:</h3>
                <?php
                    $user_id = $_SESSION['user_id'];
                    $query = "
                        SELECT w.id, w.hours, w.date, p.name AS project_name
                        FROM work_entries w
                        JOIN projects p ON w.project_id = p.id
                        WHERE w.user_id = ?
                        ORDER BY w.date DESC
                    ";

                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                    echo "<form action='index.php' method='post'>";
                    echo "<select name='record_id' required>";
                    echo "<option value='' disabled selected>Wybierz wpis do usunięcia</option>";

                    while ($row = $result->fetch_assoc()) {
                        $project_name = htmlspecialchars($row['project_name']); // Downloaded project name
                        $hours = $row['hours'];
                        $date = $row['date'];
                        echo "<option value='" . $row['id'] . "'>Projekt: $project_name | Godziny: $hours | Data: $date</option>";
                    }

                    echo "</select><br>";
                    echo "<div style='text-align: center; margin-top: 10px;'>";
                    echo "<input type='submit' name='delete' value='Usuń wpis' class='delete-btn'>";
                    echo "</div>";
                    echo "</form>";
                    } else {
                    echo "<p>Brak zapisanych godzin.</p>";
                    }
                ?>


                </form>
            </div>

            <div class="block-large">
                <h2>Podsumowanie godzin pracy</h2>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Projekt</th>
                            <th>Łączna liczba godzin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch total hours per project for the logged-in user
                        $summary_query = "
                            SELECT p.name AS project_name, SUM(w.hours) AS total_hours
                            FROM work_entries w
                            JOIN projects p ON w.project_id = p.id
                            WHERE w.user_id = ?
                            GROUP BY w.project_id
                            ORDER BY p.name ASC
                        ";
                        $summary_stmt = $conn->prepare($summary_query);
                        $summary_stmt->bind_param('i', $_SESSION['user_id']);
                        $summary_stmt->execute();
                        $summary_result = $summary_stmt->get_result();

                        if ($summary_result->num_rows > 0) {
                            while ($row = $summary_result->fetch_assoc()) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['project_name']) . '</td>';
                                echo '<td>' . number_format($row['total_hours'], 2) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="2">Brak danych do wyświetlenia</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
                <div class="block-large">
                    <h2>Statystyki godzin pracy</h2>
                    <div class="statistics-chart">
                        <?php
                        // Fetch total hours per project for the logged-in user
                        $stats_query = "
                            SELECT p.name AS project_name, SUM(w.hours) AS total_hours
                            FROM work_entries w
                            JOIN projects p ON w.project_id = p.id
                            WHERE w.user_id = ?
                            GROUP BY w.project_id
                            ORDER BY p.name ASC
                        ";
                        $stats_stmt = $conn->prepare($stats_query);
                        $stats_stmt->bind_param('i', $_SESSION['user_id']);
                        $stats_stmt->execute();
                        $stats_result = $stats_stmt->get_result();

                        // Calculate total hours across all projects
                        $total_hours_query = "
                            SELECT SUM(w.hours) AS total_hours
                            FROM work_entries w
                            WHERE w.user_id = ?
                        ";
                        $total_hours_stmt = $conn->prepare($total_hours_query);
                        $total_hours_stmt->bind_param('i', $_SESSION['user_id']);
                        $total_hours_stmt->execute();
                        $total_hours_result = $total_hours_stmt->get_result();
                        $total_hours_row = $total_hours_result->fetch_assoc();
                        $total_hours = $total_hours_row['total_hours'] ?? 0;

                        if ($stats_result->num_rows > 0 && $total_hours > 0) {
                            echo '<div class="stat-row-container">';
                            while ($row = $stats_result->fetch_assoc()) {
                                $project_name = htmlspecialchars($row['project_name']);
                                $project_hours = $row['total_hours'] ?? 0;

                                // Calculate bar width as a percentage of total hours
                                $bar_width = ($project_hours / $total_hours) * 100;

                                echo '<div class="stat-row">';
                                echo '<span class="project-name">' . $project_name . '</span>';
                                echo '<div class="progress-bar-container">';
                                echo '<div class="progress-bar" style="width: ' . $bar_width . '%;"></div>';
                                echo '<span class="progress-bar-text">' . number_format($project_hours, 2) . 'h</span>';
                                echo '</div>';
                                echo '</div>';
                            }
                            echo '</div>';
                        } else {
                            echo '<p>Brak danych do wyświetlenia</p>';
                        }
                        ?>
                    </div>
                </div>

            
        </div>
    </div>


<script src="main.js" defer></script>
</body>
</html>
