<?php
session_start();

include('db.php'); // dodajemy połączenie do bazy danych

// Jeśli użytkownik nie jest zalogowany, przekieruj na stronę logowania
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Sprawdzamy, czy użytkownik ma rolę administratora
if ($_SESSION['role'] !== 'admin') {
    echo "Brak dostępu. Tylko administrator może przeglądać tę stronę.";
    exit;
}

// Blokowanie cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Logowanie użytkownika
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Pobierz wszystkich użytkowników do wyboru wraz z max_hours_per_day
$query_users = "SELECT id, username, max_hours_per_day FROM users ORDER BY username";
$stmt_users = $conn->prepare($query_users);
$stmt_users->execute();
$result_users = $stmt_users->get_result();

// Ustawienie domyślnego użytkownika (z sesji) lub z GET, jeśli wybrano innego
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$current_month = date('Y-m');
if (isset($_GET['month'])) {
    $current_month = $_GET['month'];
}

// --- RAPORT: KTO NIE WPROWADZIŁ PEŁNYCH GODZIN WG INDYWIDUALNYCH LIMITÓW ---

$currentYear = (int)date('o');
$currentWeek = (int)date('W');

$outputMissingHours = '<div class="missing-hours-report">';
$outputMissingHours .= "<h2>Użytkownicy, którzy nie wprowadzili pełnych godzin tygodniowo (wg indywidualnych limitów) od początku roku:</h2>";
$outputMissingHours .= "<ul>";

while ($user = $result_users->fetch_assoc()) {
    $userId = $user['id'];
    $username = htmlspecialchars($user['username']);

    $max_hours_daily = isset($user['max_hours_per_day']) && $user['max_hours_per_day'] > 0 ? (float)$user['max_hours_per_day'] : 8;
    $max_hours_weekly = $max_hours_daily * 5;

    $missingWeeks = [];

    for ($week = 1; $week <= $currentWeek; $week++) {
        $year = $currentYear;

        $checkQuery = "
            SELECT IFNULL(SUM(hours), 0) as total_hours FROM work_entries
            WHERE user_id = ? AND year = ? AND week_number = ?
        ";
        $stmtCheck = $conn->prepare($checkQuery);
        $stmtCheck->bind_param('iii', $userId, $year, $week);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();
        $rowHours = $checkResult->fetch_assoc();

        $totalHours = (float)$rowHours['total_hours'];

        if ($totalHours < $max_hours_weekly) {
            $dto = new DateTime();
            $dto->setISODate($year, $week);
            $monday = $dto->format('Y-m-d');
            $dto->modify('+6 days');
            $sunday = $dto->format('Y-m-d');

            $missingWeeks[] = "Tydzień $week ($monday - $sunday) - wprowadzono $totalHours / $max_hours_weekly h";
        }
    }

    if (!empty($missingWeeks)) {
        $outputMissingHours .= "<li><strong>$username</strong><ul>";
        foreach ($missingWeeks as $mw) {
            $outputMissingHours .= "<li>Brak danych w tygodniu: $mw</li>";
        }
        $outputMissingHours .= "</ul></li>";
    }
}

$outputMissingHours .= "</ul></div>";

if (trim(strip_tags($outputMissingHours)) === 'Użytkownicy, którzy nie wprowadzili pełnych godzin tygodniowo (wg indywidualnych limitów) od początku roku:') {
    $outputMissingHours = '<div class="missing-hours-report"><p>Wszyscy użytkownicy wprowadzili wymagane godziny w każdym tygodniu od początku roku.</p></div>';
}

// Tutaj masz dane do wykresu i tabeli (nie ruszałem)
$query = "
    SELECT 
        project_name,
        SUM(hours) AS total_hours
    FROM work_entries
    GROUP BY project_name
    ORDER BY total_hours DESC
";
$stmt = $conn->prepare($query);
$stmt->execute();
$results = $stmt->get_result();

$projectsUser = [];
$hoursUser = [];
$projectRows = [];

while ($row = $results->fetch_assoc()) {
    $projectsUser[] = $row['project_name'];
    $hoursUser[] = (float)$row['total_hours'];
    $projectRows[] = $row; // używane do tabeli
}

// JSON do wykresu
$projectsUserJson = json_encode($projectsUser);
$hoursUserJson = json_encode($hoursUser);
?>



<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Czasu Pracy</title>
    <link rel="stylesheet" href="styl.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
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
            <a href="?logout=true" class="logout-btn">Wyloguj</a>
        </div>

        <div class="main">
            <div class="block large">
                <div style="text-align: center; margin-top: 10px;">
                <h1>Raport Czasu Pracy</h1>

<!-- Form: Wybór użytkownika -->
<form method="GET" action="workers_hours.php">
    <label for="user_id">Wybierz użytkownika: </label>
    <select name="user_id" id="user_id">
        <?php
        // Musimy zresetować wskaźnik wyniku, bo był już użyty poniżej!
        $result_users->data_seek(0);
        while ($row_user = $result_users->fetch_assoc()) {
            echo "<option value='" . $row_user['id'] . "'" . ($row_user['id'] == $user_id ? " selected" : "") . ">" . htmlspecialchars($row_user['username']) . "</option>";
        }
        ?>
    </select>
    <label for="month">Wybierz miesiąc: </label> <br>
    <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($current_month); ?>" />
    <button type="submit">Pokaż</button>
</form>

<?php
// Pobierz dane do wykresu użytkownika i tabeli na podstawie wybranego user_id i miesiąca
$projectsUser = [];
$hoursUser = [];
$projectRows = [];

if (!empty($user_id) && !empty($current_month)) {
    // Wyciągnij rok i miesiąc
    $year = substr($current_month, 0, 4);
    $month = substr($current_month, 5, 2);

    $query = "
        SELECT 
            project_name,
            SUM(hours) AS total_hours
        FROM work_entries
        WHERE user_id = ? AND YEAR(date) = ? AND MONTH(date) = ?
        GROUP BY project_name
        ORDER BY total_hours DESC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $user_id, $year, $month);
    $stmt->execute();
    $results = $stmt->get_result();

    while ($row = $results->fetch_assoc()) {
        $projectsUser[] = $row['project_name'];
        $hoursUser[] = (float)$row['total_hours'];
        $projectRows[] = $row;
    }
} else {
    // Brak danych
    $projectsUser = [];
    $hoursUser = [];
    $projectRows = [];
}

$projectsUserJson = json_encode($projectsUser);
$hoursUserJson = json_encode($hoursUser);
?>

<h3>Wykres godzin użytkownika za wybrany miesiąc</h3>
<canvas id="myChartUser" width="400" height="200"></canvas>

<script>
    // data from PHP to JavaScript
    const userProjects = <?php echo $projectsUserJson; ?>;
    const userHours = <?php echo $hoursUserJson; ?>;

    const ctxUser = document.getElementById('myChartUser').getContext('2d');

    const chartUser = new Chart(ctxUser, {
        type: 'bar',
        data: {
            labels: userProjects, // X axis labels (project names)
            datasets: [{
                label: 'Godziny pracy',
                data: userHours, // chartUserData (hours)
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)', 'rgba(75, 192, 192, 0.6)', 'rgba(54, 162, 235, 0.6)', 
                    'rgba(255, 159, 64, 0.6)', 'rgba(255, 205, 86, 0.6)', 'rgba(153, 102, 255, 0.6)', 
                    'rgba(41, 171, 226, 0.6)', 'rgba(255, 99, 71, 0.6)', 'rgba(0, 255, 0, 0.6)', 
                    'rgba(153, 50, 204, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)', 'rgba(75, 192, 192, 1)', 'rgba(54, 162, 235, 1)', 
                    'rgba(255, 159, 64, 1)', 'rgba(255, 205, 86, 1)', 'rgba(153, 102, 255, 1)', 
                    'rgba(41, 171, 226, 1)', 'rgba(255, 99, 71, 1)', 'rgba(0, 255, 0, 1)', 
                    'rgba(153, 50, 204, 1)'
                ],
                borderWidth: 1,
                barThickness: 30
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

<!-- Display Total Hours per Project -->
<h2>Łączna liczba godzin pracy od projektu (wybrany użytkownik i miesiąc):</h2>
<table class="workers_hours_list">
    <thead>
        <tr>
            <th>Projekt</th>
            <th>Godziny pracy</th>
        </tr>
    </thead>
    <tbody>
    <?php
        // check if there are any project rows to display
        if (!empty($projectRows)) {
            // show data in the table
            foreach ($projectRows as $row_project) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row_project['project_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row_project['total_hours']) . " godz.</td>";
                echo "</tr>";
            }
        } else {
            // If no data, display a message
            echo "<tr><td colspan='2'>Brak danych</td></tr>";
        }
        ?>
    </tbody>
</table>
<br>

</div>
<div class="block large">
    <canvas id="myChart2" height="auto"></canvas>
</div>
<div class="block large">
    <div class="workers_hours_list">
        <table class="workers_hours_list">
            <thead>
                <tr>
                    <th>Projekt</th>
                    <th>Całkowite godziny</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // UWAGA: $results jest już "wyczerpany" przez fetch_assoc powyżej!
                // Musisz wykonać zapytanie jeszcze raz, aby uzyskać dane do tej tabeli i wykresu.
                $queryAll = "
                    SELECT 
                        project_name,
                        SUM(hours) AS total_hours
                    FROM work_entries
                    GROUP BY project_name
                    ORDER BY total_hours DESC
                ";
                $stmtAll = $conn->prepare($queryAll);
                $stmtAll->execute();
                $resultAll = $stmtAll->get_result();

                $projects = [];
                $hours = [];
                while ($row = $resultAll->fetch_assoc()) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($row['project_name']) . '</td>';
                    echo '<td>' . $row['total_hours'] . ' godzin</td>';
                    echo '</tr>';
                    $projects[] = $row['project_name'];
                    $hours[] = (float)$row['total_hours'];
                }
                // Przekazujemy dane do JS
                $projectsJson = json_encode($projects);
                $hoursJson = json_encode($hours);
                ?>
            </tbody>
        </table>
        <script>
            // Otrzymane dane z PHP w formie JSON
            const projects = <?php echo $projectsJson; ?>;
            const hours = <?php echo $hoursJson; ?>;

            // Konfiguracja wykresu
            const ctx = document.getElementById('myChart2').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar', // Typ wykresu - słupkowy
                data: {
                    labels: projects, // Etykiety dla osi X
                    datasets: [{
                        data: hours,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.6)', 'rgba(75, 192, 192, 0.6)', 'rgba(54, 162, 235, 0.6)', 
                            'rgba(255, 159, 64, 0.6)', 'rgba(255, 205, 86, 0.6)', 'rgba(153, 102, 255, 0.6)', 
                            'rgba(41, 171, 226, 0.6)', 'rgba(255, 99, 71, 0.6)', 'rgba(0, 255, 0, 0.6)', 
                            'rgba(153, 50, 204, 0.6)', 'rgba(255, 99, 132, 0.6)', 'rgba(75, 192, 192, 0.6)', 
                            'rgba(54, 162, 235, 0.6)', 'rgba(255, 159, 64, 0.6)', 'rgba(255, 205, 86, 0.6)', 
                            'rgba(153, 102, 255, 0.6)', 'rgba(41, 171, 226, 0.6)', 'rgba(255, 99, 71, 0.6)', 
                            'rgba(0, 255, 0, 0.6)', 'rgba(153, 50, 204, 0.6)', 'rgba(255, 99, 132, 0.6)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)', 'rgba(75, 192, 192, 1)', 'rgba(54, 162, 235, 1)', 
                            'rgba(255, 159, 64, 1)', 'rgba(255, 205, 86, 1)', 'rgba(153, 102, 255, 1)', 
                            'rgba(41, 171, 226, 1)', 'rgba(255, 99, 71, 1)', 'rgba(0, 255, 0, 1)', 
                            'rgba(153, 50, 204, 1)', 'rgba(255, 99, 132, 1)', 'rgba(75, 192, 192, 1)', 
                            'rgba(54, 162, 235, 1)', 'rgba(255, 159, 64, 1)', 'rgba(255, 205, 86, 1)', 
                            'rgba(153, 102, 255, 1)', 'rgba(41, 171, 226, 1)', 'rgba(255, 99, 71, 1)', 
                            'rgba(0, 255, 0, 1)', 'rgba(153, 50, 204, 1)', 'rgba(255, 99, 132, 1)'
                        ],
                        borderWidth: 1,
                        barThickness: 30 // Możesz zmniejszyć wartość, aby słupki były cieńsze
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true // Oś Y zaczyna się od zera
                        }
                    }
                }
            });
        </script>

        <?php
        $currentYear = (int)date('o');
        $currentWeek = (int)date('W');

        $queryUsers = "SELECT id, username FROM users ORDER BY username";
        $stmtUsers = $conn->prepare($queryUsers);
        $stmtUsers->execute();
        $usersResult = $stmtUsers->get_result();

        $output = '<div class="missing-hours-report">';
        $output .= "<h2>Użytkownicy, którzy nie wprowadzili pełnych 40h w danym tygodniu od początku roku:</h2>";
        $output .= "<ul>";

        while ($user = $usersResult->fetch_assoc()) {
            $userId = $user['id'];
            $username = htmlspecialchars($user['username']);
            $missingWeeks = [];

            for ($week = 1; $week <= $currentWeek; $week++) {
                $year = $currentYear;

                $checkQuery = "
                    SELECT IFNULL(SUM(hours), 0) as total_hours FROM work_entries
                    WHERE user_id = ? AND year = ? AND week_number = ?
                ";
                $stmtCheck = $conn->prepare($checkQuery);
                $stmtCheck->bind_param('iii', $userId, $year, $week);
                $stmtCheck->execute();
                $checkResult = $stmtCheck->get_result();
                $rowHours = $checkResult->fetch_assoc();

                $totalHours = (float)$rowHours['total_hours'];

                if ($totalHours < 40) {
                    $dto = new DateTime();
                    $dto->setISODate($year, $week);
                    $monday = $dto->format('Y-m-d');
                    $dto->modify('+6 days');
                    $sunday = $dto->format('Y-m-d');

                    $missingWeeks[] = "Tydzień $week ($monday - $sunday) - wprowadzono $totalHours / 40 h";
                }
            }

            if (!empty($missingWeeks)) {
                $output .= "<li><strong>$username</strong><ul>";
                foreach ($missingWeeks as $mw) {
                    $output .= "<li>Brak danych w tygodniu: $mw</li>";
                }
                $output .= "</ul></li>";
            }
        }

        $output .= "</ul></div>";

        if (trim(strip_tags($output)) === '<h2>Użytkownicy, którzy nie wprowadzili pełnych 40h w danym tygodniu od początku roku:</h2><ul></ul>') {
            $output = '<div class="missing-hours-report"><p>Wszyscy użytkownicy wprowadzili pełne 40h w każdym tygodniu od początku roku.</p></div>';
        }

        echo $output;
        ?>
    </div>
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
