<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Pobierz max_hours użytkownika
$user_query = $conn->prepare("SELECT max_hours_per_day FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();
$max_hours_daily = $user_data['max_hours_per_day'] ?? 8; // domyślnie 8 jeśli null
$max_hours_weekly = $max_hours_daily * 5; // Max godzin tygodniowo

// --- ALERT HANDLING ---
$alert_message = null;

// AJAX: get week entries for edit/delete
if (isset($_GET['action']) && $_GET['action'] === 'getWeekEntries' && isset($_GET['start']) && isset($_GET['end'])) {
    $start = $_GET['start'];
    $end = $_GET['end'];
    $stmt = $conn->prepare("SELECT we.id, we.date, we.hours, p.name as project_name FROM work_entries we JOIN projects p ON we.project_id = p.id WHERE we.user_id = ? AND we.date BETWEEN ? AND ? ORDER BY we.date ASC, p.name ASC");
    $stmt->bind_param("iss", $user_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = [
            'id' => $row['id'],
            'date' => $row['date'],
            'hours' => $row['hours'],
            'project_name' => $row['project_name']
        ];
    }
    header('Content-Type: application/json');
    echo json_encode(['entries' => $entries]);
    exit;
}

// Obsługa formularza - obsługa trybu tygodniowego i dziennego razem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit-hours'])) {
    $project_id = $_POST['project'];
    $project_query = $conn->prepare("SELECT name FROM projects WHERE id = ?");
    $project_query->bind_param("i", $project_id);
    $project_query->execute();
    $project_result = $project_query->get_result();
    $project = $project_result->fetch_assoc();
    $project_name = $project['name'];

    $start = $_POST['selectedWeekStart'];
    $end = $_POST['selectedWeekEnd'];
    $year = date('Y', strtotime($start));
    $week_number = date('W', strtotime($start));
    $created_at = date('Y-m-d H:i:s');

    $startDateObj = new DateTime($start);

    if ($_POST['hourlyOrWeekly'] === 'weekly') {
        $hours = floatval($_POST['weeklyHours']);
        $totalHours = getHoursForWeek($conn, $user_id, $startDateObj);

        if ($totalHours >= 40) {
            $alert_message = 'Wprowadzono już 40h pracy na ten tydzień. Nie można dodać więcej.';
        } elseif ($totalHours + $hours > 40) {
            $hours = 40 - $totalHours;
            $alert_message = "Przekroczono maksymalną liczbę godzin tygodniowych (40h). Zostanie zaakceptowana tylko dostępna liczba godzin: $hours.";
        }

        if ($alert_message === null) {
            $dailyHours = round($hours / 5, 2);
            for ($i = 0; $i < 5; $i++) {
                $dayDate = (clone $startDateObj)->modify("+$i days")->format('Y-m-d');
                $stmt = $conn->prepare("INSERT INTO work_entries (user_id, username, project_id, project_name, date, hours, created_at, week_number, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissdsii", $user_id, $username, $project_id, $project_name, $dayDate, $dailyHours, $created_at, $week_number, $year);
                $stmt->execute();
            }
        }
        if ($alert_message) {
            $_SESSION['alert_message'] = $alert_message;
        }
        header("Location: kalendarz.php");
        exit;
    } elseif ($_POST['hourlyOrWeekly'] === 'daily') {
        // Zlicz sumę godzin dla każdego dnia tygodnia (łącznie z istniejącymi wpisami)
        $week_dates = [];
        for ($i = 0; $i < 5; $i++) {
            $week_dates[] = (clone $startDateObj)->modify("+$i days")->format('Y-m-d');
        }

        // Pobierz sumy godzin dla każdego dnia tygodnia
        $placeholders = implode(',', array_fill(0, count($week_dates), '?'));
        $types = str_repeat('s', count($week_dates));
        $params = $week_dates;
        array_unshift($params, $user_id);
        $types = 'i' . $types;

        $sql = "SELECT date, SUM(hours) as total FROM work_entries WHERE user_id = ? AND date IN ($placeholders) GROUP BY date";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing_hours = [];
        while ($row = $result->fetch_assoc()) {
            $existing_hours[$row['date']] = floatval($row['total']);
        }

        $alert_shown = false;
        $exceeded_days = [];
        foreach ($_POST['dailyHours'] as $date => $hours) {
            $hours = floatval($hours);
            if ($hours > 0) {
                $sum_hours = ($existing_hours[$date] ?? 0) + $hours;
                if ($sum_hours > $max_hours_daily) {
                    $hours = $max_hours_daily - ($existing_hours[$date] ?? 0);
                    if ($hours <= 0) {
                        // Zbieraj dni, w których nie można już dodać godzin
                        $exceeded_days[] = $date;
                        continue; // nie dodawaj jeśli przekroczono limit
                    }
                    $exceeded_days[] = $date;
                }
                $stmt = $conn->prepare("INSERT INTO work_entries (user_id, username, project_id, project_name, date, hours, created_at, week_number, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissdsii", $user_id, $username, $project_id, $project_name, $date, $hours, $created_at, $week_number, $year);
                $stmt->execute();
            }
        }
        // Dodaj alert z listą dni, gdzie nie można już dodać godzin
        if (!empty($exceeded_days)) {
            $days_str = implode(', ', $exceeded_days);
            $alert_message = "Uwaga: Przekroczono maksymalną liczbę godzin dziennych dla następujących dni: $days_str. Nie dodano więcej godzin dla tych dni.";
            $_SESSION['alert_message'] = $alert_message;
        }
        header("Location: kalendarz.php");
        exit;
    }
}

// Obsługa edycji/usuwania wpisów z tygodnia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit-entries-submit'])) {
    if (isset($_POST['edit_hours']) && is_array($_POST['edit_hours'])) {
        foreach ($_POST['edit_hours'] as $entry_id => $hours) {
            $hours = floatval($hours);
            if (isset($_POST['delete_entry'][$entry_id]) && $_POST['delete_entry'][$entry_id] == 1) {
                // Usuń wpis
                $stmt = $conn->prepare("DELETE FROM work_entries WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $entry_id, $user_id);
                $stmt->execute();
            } else {
                // Zaktualizuj godziny
                $stmt = $conn->prepare("UPDATE work_entries SET hours = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param("dii", $hours, $entry_id, $user_id);
                $stmt->execute();
            }
        }
    }
    header("Location: kalendarz.php");
    exit;
}

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$weeks_by_month = [];

// Szukamy 1 stycznia danego roku
$firstJan = strtotime("$year-01-01");
// Szukamy 31 grudnia danego roku
$lastDec = strtotime("$year-12-31");

// Cofamy się do poniedziałku tygodnia zawierającego 1 stycznia
$dayOfWeek = date('N', $firstJan); // 1 = poniedziałek, ..., 7 = niedziela
$startDate = strtotime("-" . ($dayOfWeek - 1) . " days", $firstJan);

// Zmienna do śledzenia tygodni po dacie początkowej, aby nie dublować tygodni
$added_weeks = [];

// Będziemy generować tygodnie dopóki tydzień zawiera jakikolwiek dzień z danego roku
while ($startDate <= $lastDec) {
    $weekStart = date('Y-m-d', $startDate);
    $weekEnd = date('Y-m-d', strtotime('+4 days', $startDate)); // Piątek

    // Sprawdzamy, czy tydzień zawiera jakiś dzień z danego roku (np. wtorek 2025-01-01)
    $containsYear = false;
    for ($i = 0; $i <= 4; $i++) {
        $checkDate = strtotime("+$i days", $startDate);
        if (date('Y', $checkDate) == $year) {
            $containsYear = true;
            break;
        }
    }

    // Jeśli tydzień ma przynajmniej jeden dzień z danego roku, dodajemy
    // oraz nie został już dodany (po dacie początkowej)
    if ($containsYear && !in_array($weekStart, $added_weeks)) {
        $month = intval(date('n', $startDate)); // miesiąc wg poniedziałku
        $weeks_by_month[$month][] = "$weekStart do $weekEnd";
        $added_weeks[] = $weekStart;
    }

    // Następny tydzień
    $startDate = strtotime('+1 week', $startDate);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['entries'])) {
    foreach ($_POST['entries'] as $entry) {
        $project_id = $entry['project_id'];
        foreach ($entry['hours'] as $date => $hours) {
            $hours = floatval($hours);
            if ($hours > 0) {
                $stmt = $conn->prepare("SELECT id FROM work_entries WHERE user_id = ? AND project_id = ? AND date = ?");
                $stmt->bind_param("iis", $user_id, $project_id, $date);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    // Zaktualizuj istniejący wpis
                    $stmt_update = $conn->prepare("UPDATE work_entries SET hours = ? WHERE user_id = ? AND project_id = ? AND date = ?");
                    $stmt_update->bind_param("diis", $hours, $user_id, $project_id, $date);
                    $stmt_update->execute();
                } else {
                    // Dodaj nowy wpis
                    $stmt_insert = $conn->prepare("INSERT INTO work_entries (user_id, project_id, date, hours) VALUES (?, ?, ?, ?)");
                    $stmt_insert->bind_param("iisd", $user_id, $project_id, $date, $hours);
                    $stmt_insert->execute();
                }
            }
        }
    }
    header("Location: kalendarz.php");
    exit();
}

// Pobieranie projektów
$projects = $conn->query("SELECT id, name FROM projects");

// Pobieranie wpisów do podglądu
$entries = [];
$result = $conn->query("SELECT p.name, we.project_id, we.date, we.hours FROM work_entries we JOIN projects p ON we.project_id = p.id WHERE we.user_id = $user_id ORDER BY we.date DESC");
while ($row = $result->fetch_assoc()) {
    $week = date("o-W", strtotime($row['date']));
    $entries[$week][$row['project_id']]['name'] = $row['name'];
    $entries[$week][$row['project_id']]['hours'][$row['date']] = $row['hours'];
}

function getStartOfWeek($date) {
    $dt = new DateTime($date);
    $dt->modify('monday this week');
    return $dt;
}

function getHoursForWeek($conn, $user_id, $startOfWeek) {
    $start = $startOfWeek->format('Y-m-d');
    $end = clone $startOfWeek;
    $end->modify('+6 days');
    $end = $end->format('Y-m-d');

    $sql = "SELECT SUM(hours) AS total FROM work_entries WHERE user_id = ? AND date BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    return $result['total'] ?? 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['weekly_hours_submit'])) {
    $week_date = $_POST['week_date'];
    $hours = (int)$_POST['weekly_hours'];
    $start = getStartOfWeek($week_date);

    // najpierw usuń poprzednie wpisy z tego tygodnia
    $end = (clone $start)->modify('+4 days')->format('Y-m-d');
    $delete = $conn->prepare("DELETE FROM work_entries WHERE user_id = ? AND date BETWEEN ? AND ?");
    $start_str = $start->format('Y-m-d');
    $delete->bind_param("iss", $user_id, $start_str, $end);

    $delete->execute();

    // rozdziel na 5 dni roboczych
    $daily = round($hours / 5, 2);
    for ($i = 0; $i < 5; $i++) {
        $day = (clone $start)->modify("+$i days")->format('Y-m-d');
        $stmt = $conn->prepare("INSERT INTO work_entries (user_id, project_id, date, hours) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisd", $user_id, $project_id, $day, $daily);
        $stmt->execute();
    }

    header("Location: kalendarz.php");
    exit;
}

$monthsPL = [
    1 => 'Styczeń', 2 => 'Luty', 3 => 'Marzec', 4 => 'Kwiecień',
    5 => 'Maj', 6 => 'Czerwiec', 7 => 'Lipiec', 8 => 'Sierpień',
    9 => 'Wrzesień', 10 => 'Październik', 11 => 'Listopad', 12 => 'Grudzień'
];

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- ALERT OUTPUT (for JS) ---
if (isset($_SESSION['alert_message'])) {
    echo "<script>
        window.addEventListener('DOMContentLoaded', function() {
            showAlert('" . addslashes($_SESSION['alert_message']) . "');
        });
    </script>";
    unset($_SESSION['alert_message']);
}
?>
<!-- Alert container for JS alerts -->
<div id="top-alert" style="display:none;position:fixed;top:0;left:0;width:100%;z-index:9999;text-align:center;">
    <div style="display:inline-block;background:#ffcccc;color:#a00;padding:10px 30px;margin:10px auto;border:1px solid #a00;border-radius:4px;font-weight:bold;" id="top-alert-msg"></div>
</div>
<script>
function showAlert(msg) {
    var alertBox = document.getElementById('top-alert');
    var alertMsg = document.getElementById('top-alert-msg');
    alertMsg.textContent = msg;
    alertBox.style.display = 'block';
    setTimeout(function() {
        alertBox.style.display = 'none';
    }, 3500);
}
</script>
 
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETA Kalendarz pracy</title>
    <link rel="stylesheet" href="styl.css">
    <link rel="shortcut icon" href="ETA-logo.jpg type="image/x-icon">
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
                <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"">
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
            <a href="?logout=true" class="logout-btn">Wyloguj</a>
        </div>
 
<div id="calendar">
    <div class="calendar-header">
        <button id="prev-month" onclick="changeYear(-1)">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                <path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/>
            </svg>
        </button>
        <span class="month-year" id="year-display"><?php echo $year; ?></span>
        <button id="next-month" onclick="changeYear(1)">
            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                <path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/>
            </svg>
        </button>
    </div>
<div class="weeks-container">
    <?php
    ksort($weeks_by_month);
    if (isset($weeks_by_month[12])) {
        $december = $weeks_by_month[12];
        unset($weeks_by_month[12]);
        $weeks_by_month[12] = $december;
    }
    $shownWeeks = [];
    foreach ($weeks_by_month as $monthNum => $weeks) {
        $monthName = $monthsPL[intval($monthNum)];
        echo "<div class='month-container'>";
        echo "<h4 class='month-title'>$monthName</h4>";
        echo "<div class='weeks-group'>";
        foreach ($weeks as $index => $week) {
            $weekParts = explode(' do ', $week);
            $startDate = $weekParts[0];
            $endDate = $weekParts[1];

            // Zamiast sprawdzać rok po starcie tygodnia, bierzemy pod uwagę rok końca tygodnia
            $endYear = date('Y', strtotime($endDate));

            // Sprawdzamy, czy tydzień kończy się w aktualnym roku, który wyświetlamy
            if ($endYear != $year) continue;

            // Zapobiegamy powtórkom tygodni wg daty końca tygodnia
            if (in_array($endDate, $shownWeeks)) continue;

            $shownWeeks[] = $endDate;

            $startOfWeek = new DateTime($startDate);
            $weekNumber = $startOfWeek->format('W');
            $totalHours = getHoursForWeek($conn, $user_id, $startOfWeek);
            $status_class = 'future';
            $isPast = strtotime($startDate) < strtotime(date('Y-m-d'));
            if ($totalHours == 40) {
                $status_class = 'complete';
            } elseif ($totalHours > 0 && $totalHours < 40) {
                $status_class = 'partial';
            } elseif ($isPast && $totalHours == 0) {
                $status_class = 'missed';
            }
            echo "<div class='week-item $status_class' onclick=\"setWeekDates('$startDate', '$endDate')\">
                <div class='week-title'>Tydzień $weekNumber</div>
                <div class='week-dates'>$startDate – $endDate</div>
                <div class='week-hours'>$totalHours / $max_hours_weekly godz.</div>
            </div>";
        }
        echo "</div>";
        echo "</div>";
    }
    ?>
</div>


<!-- Modal -->
<div id="workModal" class="modal">
    <div class="modal-content">
        <span id="closeModalBtn" class="close">&times;</span>
        <h2>Wprowadź godziny pracy</h2>
        <form method="post" action="kalendarz.php" id="addHoursForm">
            <input type="hidden" id="selectedWeekStart" name="selectedWeekStart">
            <input type="hidden" id="selectedWeekEnd" name="selectedWeekEnd">
            <label for="project">Projekt:</label>
            <select id="project" name="project" required>
            <option value="" disabled selected>Wybierz projekt</option>
            <?php
            $result = $conn->query("SELECT id, name FROM projects ORDER BY name ASC");
            while ($row = $result->fetch_assoc()) {
                echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
            }
            ?>
            </select><br>

            <label for="hourlyOrWeekly">Tryb wprowadzenia:</label>
            <select id="hourlyOrWeekly" name="hourlyOrWeekly" required>
            <option value="weekly">Tygodniowo</option>
            <option value="daily">Dziennie</option>
            </select><br>

            <div id="weeklyHoursContainer" class="hours-container">
            <label for="weeklyHours">Godziny tygodniowe:</label>
            <input type="number" id="weeklyHours" name="weeklyHours" min="0" max="<?= $max_hours_weekly ?>" step="0.5">
            <small style='font-size: 12px;'>Max: <?= $max_hours_weekly ?> godz.</small><br>
            </div>

            <div id="dailyHoursContainer" class="hours-container" style="display:none;">
            <?php
            // Prepare to fetch hours for each day of the selected week
            // Default: no coloring, will be filled by JS after week selection
            $days = [
                'monday' => 'Pon.',
                'tuesday' => 'Wt.',
                'wednesday' => 'Śr.',
                'thursday' => 'Czw.',
                'friday' => 'Pt.'
            ];
            foreach ($days as $dayKey => $dayLabel) {
                // Each input gets a dynamic class for coloring, to be set by JS
                echo '<div class="day-input" id="dayinput-'.$dayKey.'" data-day="'.$dayKey.'" data-existing-hours="0">';
                echo '<input type="number" id="'.$dayKey.'" name="dailyHours[]" min="0" max="'.$max_hours_daily.'" step="0.5" class="day-hours-input" data-day="'.$dayKey.'">';
                echo '<label for="'.$dayKey.'">'.$dayLabel.'</label>';
                echo '<small data-day="'.$dayKey.'" style="font-size: 12px;">Data: --</small><br>';
                echo '<span class="day-hours-indicator" data-day="'.$dayKey.'" style="font-size:12px;">0/'.$max_hours_daily.'h</span>';
                echo '</div>';

            }
            ?>
            </div>
            <script>
            // Fetch all days' hours for the selected week and color .day-input divs
            function colorDayInputs(startDateStr) {
            const [year, month, day] = startDateStr.split('-').map(Number);
            if (!year || !month || !day) return;
            const startDate = new Date(year, month - 1, day);
            const dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            dayKeys.forEach((dayKey, index) => {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + index);
                const yyyy = date.getFullYear();
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const dd = String(date.getDate()).padStart(2, '0');
                const formattedDate = `${yyyy}-${mm}-${dd}`;
                const inputDiv = document.getElementById('dayinput-' + dayKey);
                if (inputDiv) {
                // Remove previous color classes
                inputDiv.classList.remove('day-complete', 'day-partial', 'day-missed', 'day-future');
                // Fetch hours for this day
                fetch('kalendarz.php?action=getWeekEntries&start=' + encodeURIComponent(formattedDate) + '&end=' + encodeURIComponent(formattedDate))
                    .then(response => response.json())
                    .then(data => {
                    let hours = 0;
                    if (data && data.entries && data.entries.length > 0) {
                        data.entries.forEach(entry => {
                        hours += parseFloat(entry.hours);
                        });
                    }
                    // Color logic
                    if (hours >= <?= $max_hours_daily ?>) {
                        inputDiv.classList.add('day-complete');
                    } else if (hours > 0 && hours < <?= $max_hours_daily ?>) {
                        inputDiv.classList.add('day-partial');
                    } else {
                        // Missed if in the past, future otherwise
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        if (date < today) {
                        inputDiv.classList.add('day-missed');
                        } else {
                        inputDiv.classList.add('day-future');
                        }
                    }
                    });
                }
            });
            }

            // Hook into week selection
            function fillDailyDates(startDateStr) {
            const [year, month, day] = startDateStr.split('-').map(Number);
            if (!year || !month || !day) return;
            const startDate = new Date(year, month - 1, day);
            const dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            dayNames.forEach((day, index) => {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + index);
                const yyyy = date.getFullYear();
                const mm = String(date.getMonth() + 1).padStart(2, '0');
                const dd = String(date.getDate()).padStart(2, '0');
                const formattedDate = `${yyyy}-${mm}-${dd}`;
                const input = document.getElementById(day);
                if (input) {
                input.name = `dailyHours[${formattedDate}]`;
                }
                const label = document.querySelector(`small[data-day="${day}"]`);
                if (label) {
                label.textContent = `${formattedDate} | Max: <?= $max_hours_daily ?> godz.`;
                }
            });
            // Color inputs after filling dates
            colorDayInputs(startDateStr);
            }
            </script>
            <style>
            /* Example coloring, adjust as needed */
            .day-input.day-complete { background: #c8e6c9; }
            .day-input.day-partial  { background: #fff9c4; }
            .day-input.day-missed   { background: #ffcdd2; }
            .day-input.day-future   { background: #e3e3e3; }
            </style>

            <div style="text-align: center; margin-top: 10px;">
            <input type="submit" name="submit-hours" value="Zatwierdź" class="submit-btn">
            </div>
        </form>

        <!-- EDIT/DELETE FORM: will be filled dynamically -->
        <div id="editEntriesContainer" style="margin-top:30px; display:none;">
            <h3>Edytuj/Usuń wpisy z wybranego tygodnia</h3>
            <form method="post" action="kalendarz.php" id="editEntriesForm">
                <input type="hidden" name="editWeekStart" id="editWeekStart">
                <input type="hidden" name="editWeekEnd" id="editWeekEnd">
                <div id="editEntriesTable"></div>
                <div style="margin-top:10px;">
                    <input type="submit" name="edit-entries-submit" value="Zapisz zmiany" class="submit-btn">
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeYear(offset) {
    const currentYear = <?php echo $year; ?>;
    const newYear = currentYear + offset;
    if (newYear >= 2020 && newYear <= 2030) {
        window.location.href = `kalendarz.php?year=${newYear}`;
    }
}

function setWeekDates(startDate, endDate) {
    document.getElementById('selectedWeekStart').value = startDate;
    document.getElementById('selectedWeekEnd').value = endDate;
    document.getElementById('workModal').style.display = 'block';
    fillDailyDates(startDate);
    // Load entries for edit/delete
    loadWeekEntries(startDate, endDate);
}

const modal = document.getElementById('workModal');
const closeModalBtn = document.getElementById('closeModalBtn');

closeModalBtn.addEventListener('click', () => {
    modal.style.display = 'none';
    document.getElementById('editEntriesContainer').style.display = 'none';
    document.getElementById('editEntriesTable').innerHTML = '';
});

window.onclick = function(event) {
    if (event.target === modal) {
        modal.style.display = 'none';
        document.getElementById('editEntriesContainer').style.display = 'none';
        document.getElementById('editEntriesTable').innerHTML = '';
    }
};

document.getElementById('hourlyOrWeekly').addEventListener('change', function() {
    var mode = this.value;
    if (mode === 'weekly') {
        document.getElementById('weeklyHoursContainer').style.display = 'block';
        document.getElementById('dailyHoursContainer').style.display = 'none';
    } else {
        document.getElementById('weeklyHoursContainer').style.display = 'none';
        document.getElementById('dailyHoursContainer').style.display = 'block';
    }
});

function fillDailyDates(startDateStr) {
    const [year, month, day] = startDateStr.split('-').map(Number);
    if (!year || !month || !day) return;
    const startDate = new Date(year, month - 1, day);
    const dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    dayNames.forEach((day, index) => {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + index);
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const formattedDate = `${yyyy}-${mm}-${dd}`;
        const input = document.getElementById(day);
        if (input) {
            input.name = `dailyHours[${formattedDate}]`;
        }
        const label = document.querySelector(`small[data-day="${day}"]`);
        if (label) {
            label.textContent = `${formattedDate} | Max: <?= $max_hours_daily ?> godz.`;
        }
    });
}

window.addEventListener('DOMContentLoaded', () => {
    modal.style.display = 'none';
});

// AJAX: Load week entries for edit/delete
function loadWeekEntries(startDate, endDate) {
    fetch('kalendarz.php?action=getWeekEntries&start=' + encodeURIComponent(startDate) + '&end=' + encodeURIComponent(endDate))
        .then(response => response.json())
        .then(data => {
            if (data && data.entries && data.entries.length > 0) {
                document.getElementById('editEntriesContainer').style.display = 'block';
                document.getElementById('editWeekStart').value = startDate;
                document.getElementById('editWeekEnd').value = endDate;
                let html = '<table><tr><th>Data</th><th>Projekt</th><th>Godziny</th><th>Usuń</th></tr>';
                data.entries.forEach(entry => {
                    html += `<tr>
                        <td>${entry.date}</td>
                        <td>${entry.project_name}</td>
                        <td>
                            <input type="number" name="edit_hours[${entry.id}]" value="${entry.hours}" min="0" max="<?= $max_hours_daily ?>" step="0.5">
                        </td>
                        <td class="delete-cell">
                            <label class="custom-checkbox">
                                <input type="checkbox" name="delete_entry[${entry.id}]" value="1" />
                                <span class="checkmark"></span>
                            </label>
                        </td>
                    </tr>`;
                });
                html += '</table>';
                document.getElementById('editEntriesTable').innerHTML = html;
            } else {
                document.getElementById('editEntriesContainer').style.display = 'none';
                document.getElementById('editEntriesTable').innerHTML = '';
            }
        });
}

</script>
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


// --- LIVE DAY STATUS COLORING & INDICATOR ---
// This function fetches hours from DB for the selected week and updates coloring/indicators live as user types.

let weekDayDates = []; // will be filled by fillDailyDates

function fetchAndSetDayStatuses(startDateStr) {
    // Fill weekDayDates with dates for Mon-Fri
    weekDayDates = [];
    const [year, month, day] = startDateStr.split('-').map(Number);
    if (!year || !month || !day) return;
    const startDate = new Date(year, month - 1, day);
    for (let i = 0; i < 5; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        weekDayDates.push(`${yyyy}-${mm}-${dd}`);
    }

    // Fetch all entries for the week in one AJAX call
    fetch('kalendarz.php?action=getWeekEntries&start=' + encodeURIComponent(weekDayDates[0]) + '&end=' + encodeURIComponent(weekDayDates[4]))
        .then(response => response.json())
        .then(data => {
            // Map: date => sum of hours
            const dbHours = {};
            if (data && data.entries) {
                data.entries.forEach(entry => {
                    if (!dbHours[entry.date]) dbHours[entry.date] = 0;
                    dbHours[entry.date] += parseFloat(entry.hours);
                });
            }
            // Set data-existing-hours attribute for each day
            const dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            dayKeys.forEach((dayKey, idx) => {
                const date = weekDayDates[idx];
                const wrapper = document.getElementById('dayinput-' + dayKey);
                if (wrapper) {
                    wrapper.dataset.existingHours = dbHours[date] || 0;
                }
            });
            // After setting, update live status
            updateLiveDayStatus();
        });
}

// Update coloring and indicators live as user types
function updateLiveDayStatus() {
    const max = <?= $max_hours_daily ?>;
    const dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    dayKeys.forEach((dayKey, idx) => {
        const wrapper = document.getElementById('dayinput-' + dayKey);
        if (!wrapper) return;
        const input = document.getElementById(dayKey);
        const indicator = document.querySelector(`.day-hours-indicator[data-day="${dayKey}"]`);
        const existing = parseFloat(wrapper.dataset.existingHours) || 0;
        const inputVal = parseFloat(input?.value) || 0;
        const total = existing + inputVal;

        // Remove old color classes
        wrapper.classList.remove('day-complete', 'day-partial', 'day-missed', 'day-future');

        // Add color class
        if (total >= max) {
            wrapper.classList.add('day-complete');
        } else if (total > 0 && total < max) {
            wrapper.classList.add('day-partial');
        } else {
            // Missed if in the past, future otherwise
            const today = new Date();
            today.setHours(0,0,0,0);
            const dateStr = weekDayDates[idx];
            const dateObj = new Date(dateStr);
            if (dateObj < today) {
                wrapper.classList.add('day-missed');
            } else {
                wrapper.classList.add('day-future');
            }
        }

        // Update indicator
        if (indicator) {
            indicator.textContent = `${total}/${max}h`;
        }
    });
}

// Hook into week selection to fetch DB hours and update
function fillDailyDates(startDateStr) {
    // (original code for setting names/labels)
    const [year, month, day] = startDateStr.split('-').map(Number);
    if (!year || !month || !day) return;
    const startDate = new Date(year, month - 1, day);
    const dayNames = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    dayNames.forEach((day, index) => {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + index);
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const formattedDate = `${yyyy}-${mm}-${dd}`;
        const input = document.getElementById(day);
        if (input) {
            input.name = `dailyHours[${formattedDate}]`;
            input.value = ''; // clear on open
        }
        const label = document.querySelector(`small[data-day="${day}"]`);
        if (label) {
            label.textContent = `${formattedDate} | Max: <?= $max_hours_daily ?> godz.`;
        }
    });
    // Fetch DB hours and update coloring/indicators
    fetchAndSetDayStatuses(startDateStr);
}

// Listen for input changes to update coloring/indicators live
document.addEventListener('input', function (e) {
    if (e.target.classList.contains('day-hours-input')) {
        updateLiveDayStatus();
    }
});

// On modal open, always fetch DB hours and update
window.addEventListener('DOMContentLoaded', () => {
    // Modal is hidden by default, so nothing to do here
    // Coloring will be set on fillDailyDates (on week click)
});


</script>
</body>
</html>
