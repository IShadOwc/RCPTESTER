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

// Pobierz wszystkich użytkowników do wyboru
$query_users = "SELECT id, username FROM users";
$stmt_users = $conn->prepare($query_users);
$stmt_users->execute();
$result_users = $stmt_users->get_result();

// Ustawienie domyślnego użytkownika (z sesji) lub z GET, jeśli wybrano innego
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_SESSION['user_id'];
$current_month = date('Y-m');
if (isset($_GET['month'])) {
    $current_month = $_GET['month'];
}

// Pobierz dane godzin pracy dla wybranego użytkownika w wybranym miesiącu
$query = "
    SELECT SUM(we.hours) AS total_hours 
    FROM work_entries we
    WHERE we.user_id = ? AND DATE_FORMAT(we.date, '%Y-%m') = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('is', $user_id, $current_month);
$stmt->execute();
$result = $stmt->get_result();
$total_hours = 0;

if ($row = $result->fetch_assoc()) {
    $total_hours = $row['total_hours'];
}

// Pobierz godziny pracy per projekt
$query_projects = "
    SELECT p.id AS project_id, p.name AS project_name, SUM(we.hours) AS total_hours
    FROM work_entries we
    JOIN projects p ON we.project_id = p.id
    WHERE we.user_id = ? AND DATE_FORMAT(we.date, '%Y-%m') = ?
    GROUP BY p.id ORDER BY total_hours DESC
";
$stmt_projects = $conn->prepare($query_projects);
$stmt_projects->bind_param('is', $user_id, $current_month);
$stmt_projects->execute();
$result_projects = $stmt_projects->get_result();

// Buforowanie danych
$projectRows = [];

if ($result_projects && $result_projects->num_rows > 0) {
    while ($row_project = $result_projects->fetch_assoc()) {
        $projectRows[] = $row_project;
    }
}

$projectsUser = [];
$hoursUser = [];

while ($row = $result_projects->fetch_assoc()) {
    $projectsUser[] = $row['project_name'];
    $hoursUser[] = (float)$row['total_hours'];
}

$projectsUserJson = json_encode($projectsUser);
$hoursUserJson = json_encode($hoursUser);


// Zapytanie SQL: pobieranie godzin pracy dla wszystkich użytkowników
$query = "
    SELECT 
        we.user_id,
        we.username,
        p.name AS project_name,
        SUM(we.hours) AS total_hours
    FROM 
        work_entries we
    JOIN 
        projects p ON we.project_id = p.id
    GROUP BY 
        we.user_id, we.username, p.name
    ORDER BY 
        total_hours DESC
";
$stmt = $conn->prepare($query);
$stmt->execute();
$results = $stmt->get_result();

// Pobieranie wyników
$projects = [];
$hours = [];

while ($row = $results->fetch_assoc()) {
    $projects[] = $row['project_name']; // Nazwa projektu
    $hours[] = (float)$row['total_hours']; // Całkowita liczba godzin
}

// Przekaż dane projektów i godzin dla wszystkich użytkowników
$projectsJson = json_encode($projects);
$hoursJson = json_encode($hours);

// Przekaż dane projektów i godzin dla wybranego użytkownika
$projectsUserJson = json_encode($projectsUser);
$hoursUserJson = json_encode($hoursUser);

// Zapytanie SQL: użytkownicy, którzy nie wprowadzili godzin pracy w ostatnim miesiącu
$query_missing_hours = "
    SELECT u.id, u.username, 
           DATE_SUB(CURDATE(), INTERVAL n.n DAY) AS missing_date
    FROM users u
    CROSS JOIN (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL 
                SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL 
                SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL 
                SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL 
                SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL 
                SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL 
                SELECT 24 UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL 
                SELECT 28) n
    LEFT JOIN work_entries we ON u.id = we.user_id 
                               AND DATE(we.created_at) = DATE_SUB(CURDATE(), INTERVAL n.n DAY)
    WHERE we.id IS NULL
    AND u.id NOT IN (SELECT DISTINCT user_id FROM work_entries WHERE created_at >= CURDATE() - INTERVAL 1 MONTH)
    ORDER BY u.username, missing_date;
";

$stmt_missing_hours = $conn->prepare($query_missing_hours);
$stmt_missing_hours->execute();
$result_missing_hours = $stmt_missing_hours->get_result();

// Zmienna do wyświetlania wyników w divie
$output = "";

if ($result_missing_hours->num_rows > 0) {
    $output .= "<h2>Użytkownicy, którzy nie wprowadzili godzin pracy w określonych dniach w ostatnim miesiącu:</h2>";
    $output .= "<ul>";
    $current_user = "";
    while ($row = $result_missing_hours->fetch_assoc()) {
        $username = htmlspecialchars($row['username']);
        $missing_date = $row['missing_date'];

        // Jeżeli zmienia się użytkownik, to wypisz jego nazwę
        if ($current_user != $username) {
            if ($current_user != "") {
                $output .= "</ul>";
            }
            $output .= "<li><strong>$username</strong>";
            $output .= "<ul>";
            $current_user = $username;
        }

        // Wyświetl daty, w które brakowało danych
        $output .= "<li>Brak danych w dniu: $missing_date</li>";
    }
    $output .= "</ul>";
    $output .= "</ul>";
} else {
    $output .= "<p>Wszyscy użytkownicy wprowadzili godziny pracy w ostatnim miesiącu.</p>";
}
?>


<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETA Raport Czasu Pracy</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="ETA-logo.jpg" type="image/x-icon"> 
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
            function toggleTheme() {
            const body = document.body;
            body.classList.toggle('dark-theme');
            const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
            localStorage.setItem('theme', currentTheme);
            setCookie('theme', currentTheme, 30); // Save theme in a cookie for 30 days

            // Update SVG path based on theme
            const themePath = document.getElementById('theme-path');
            if (currentTheme === 'dark') {
                themePath.setAttribute('d', 'M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Zm0-80q88 0 158-48.5T740-375q-20 5-40 8t-40 3q-123 0-209.5-86.5T364-660q0-20 3-40t8-40q-78 32-126.5 102T200-480q0 116 82 198t198 82Zm-10-270Z');
            } else {
                themePath.setAttribute('d', 'M480-360q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35Zm0 80q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Zm326-268Z');
            }
            }

            // Apply saved theme on page load
            document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = getCookie('theme') || localStorage.getItem('theme');
            const body = document.body;
            const themePath = document.getElementById('theme-path');

            if (savedTheme === 'dark') {
                body.classList.add('dark-theme');
                themePath.setAttribute('d', 'M480-120q-150 0-255-105T120-480q0-150 105-255t255-105q14 0 27.5 1t26.5 3q-41 29-65.5 75.5T444-660q0 90 63 153t153 63q55 0 101-24.5t75-65.5q2 13 3 26.5t1 27.5q0 150-105 255T480-120Zm0-80q88 0 158-48.5T740-375q-20 5-40 8t-40 3q-123 0-209.5-86.5T364-660q0-20 3-40t8-40q-78 32-126.5 102T200-480q0 116 82 198t198 82Zm-10-270Z');
            } else {
                themePath.setAttribute('d', 'M480-360q50 0 85-35t35-85q0-50-35-85t-85-35q-50 0-85 35t-35 85q0 50 35 85t85 35Zm0 80q-83 0-141.5-58.5T280-480q0-83 58.5-141.5T480-680q83 0 141.5 58.5T680-480q0 83-58.5 141.5T480-280ZM200-440H40v-80h160v80Zm720 0H760v-80h160v80ZM440-760v-160h80v160h-80Zm0 720v-160h80v160h-80ZM256-650l-101-97 57-59 96 100-52 56Zm492 496-97-101 53-55 101 97-57 59Zm-98-550 97-101 59 57-100 96-56-52ZM154-212l101-97 55 53-97 101-59-57Zm326-268Z');
            }
            });
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
        while ($row_user = $result_users->fetch_assoc()) {
            echo "<option value='" . $row_user['id'] . "'" . ($row_user['id'] == $user_id ? " selected" : "") . ">" . htmlspecialchars($row_user['username']) . "</option>";
        }
        ?>
    </select>
    <label for="month">Wybierz miesiąc: </label> <br>
    <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($current_month); ?>" />
    <button type="submit">Pokaż</button>
</form>

<h3>Wykres godzin użytkownika za wybrany miesiąc</h3>
<canvas id="myChartUser" width="400" height="200"></canvas>

<script>
    // data from PHP to JavaScript
    const userProjects = <?php echo $projectsUserJson; ?>;
    const userHours = <?php echo $hoursUserJson; ?>;

    // Debugging: check if data is correct
    console.log('Projects:', userProjects);
    console.log('Hours:', userHours);

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
 <h2>Łączna liczba godzin pracy od projektu:</h2>
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
    // Generowanie tabeli na podstawie wyników
    foreach ($results as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($row['project_name']) . '</td>';
        echo '<td>' . $row['total_hours'] . ' godzin</td>';
        echo '</tr>';
    }
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

        <?php echo $output; ?>
                    </div>
                </div>
            
            </div>
        </div>
</body>
</html>
