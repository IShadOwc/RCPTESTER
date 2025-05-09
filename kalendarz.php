<?php
session_start();
require_once 'db.php';
 
require 'vendor/autoload.php';

 
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
            echo "<script>alert('Wprowadzono ju\u017c 40h pracy na ten tydzie\u0144. Nie mo\u017cna doda\u0107 wi\u0119cej.'); window.location.href='kalendarz.php';</script>";
            exit;
        }

        if ($totalHours + $hours > 40) {
            $hours = 40 - $totalHours;
        }

        $dailyHours = round($hours / 5, 2);
        for ($i = 0; $i < 5; $i++) {
            $dayDate = (clone $startDateObj)->modify("+$i days")->format('Y-m-d');
            $stmt = $conn->prepare("INSERT INTO work_entries (user_id, username, project_id, project_name, date, hours, created_at, week_number, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isissdsii", $user_id, $username, $project_id, $project_name, $dayDate, $dailyHours, $created_at, $week_number, $year);
            $stmt->execute();
        }

        header("Location: kalendarz.php");
        exit;
    } elseif ($_POST['hourlyOrWeekly'] === 'daily') {
        foreach ($_POST['dailyHours'] as $date => $hours) {
            $hours = floatval($hours);
            if ($hours > 0) {
                $stmt = $conn->prepare("INSERT INTO work_entries (user_id, username, project_id, project_name, date, hours, created_at, week_number, year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissdsii", $user_id, $username, $project_id, $project_name, $date, $hours, $created_at, $week_number, $year);
                $stmt->execute();
            }
        }
        header("Location: kalendarz.php");
        exit;
    }
}


 
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$weeks_by_month = [];
 
// Ustawiamy pierwszy poniedziałek roku
$startDate = strtotime("first Monday of January $year");
 
// Generujemy tygodnie do końca grudnia
while (date('Y', $startDate) <= $year) {
    $weekStart = date('Y-m-d', $startDate);
    $weekEnd = date('Y-m-d', strtotime('+4 days', $startDate)); // Piątek tego tygodnia
 
    // Pobieramy miesiąc (na podstawie poniedziałku)
    $month = intval(date('n', $startDate));
 
    // Dodajemy tydzień do tablicy danego miesiąca
    $weeks_by_month[$month][] = "$weekStart do $weekEnd";
 
    // Przechodzimy do kolejnego tygodnia
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
    $startDate = $startOfWeek->format('Y-m-d');
    $endDate = $startOfWeek->modify('+6 days')->format('Y-m-d');

    $stmt = $conn->prepare("SELECT SUM(hours) as total_hours FROM work_entries WHERE user_id = ? AND date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $user_id, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total_hours'] ?? 0;
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
 
?>
 
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ETA Kalendarz pracy</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="ETA-logo.jpg type="image/x-icon">
    <style>
        .week-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .week-item:hover {
            opacity: 0.8;
        }
        .week-item.future {
            background-color: white;
            border: 1px solid #ddd;
        }
        .week-item.missed {
            background-color: #ff6b6b;
            color: white;
        }
        .week-item.partial {
            background-color: #ffd166;
        }
        .week-item.complete {
            background-color: #06d6a0;
            color: white;
        }
        .month-container {
            margin-bottom: 20px;
        }
        .month-title {
            margin: 10px 0;
        }
        .weeks-group {
            display: flex;
            flex-direction: column;
        }
    </style>
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
        <div id="calendar">
            <div class="calendar-header">
                <button id="prev-month" onclick="changeYear(-1)"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M560-240 320-480l240-240 56 56-184 184 184 184-56 56Z"/></svg></button>
                <span class="month-year" id="year-display"><?php echo $year; ?></span>
                <button id="next-month" onclick="changeYear(1)"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#000000"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg></button>
            </div>
<div class="weeks-container">
<?php
foreach ($weeks_by_month as $monthNum => $weeks) {
    $monthName = $monthsPL[intval($monthNum)];
    echo "<div class='month-container'>"; // Dodanie div dla całego miesiąca
    echo "<h4 class='month-title'>$monthName</h4>";
    echo "<div class='weeks-group'>";
 
    foreach ($weeks as $index => $week) {
        $weekParts = explode(' do ', $week);
        $startDate = $weekParts[0];
        $endDate = $weekParts[1];
 
        // Pobierz sumę godzin dla tego tygodnia
        $startOfWeek = new DateTime($startDate);
        $weekNumber = $startOfWeek->format('W'); // Pobiera numer tygodnia
        $totalHours = getHoursForWeek($conn, $user_id, $startOfWeek); // Pobiera sumę godzin
 
        // Domyślnie - przyszły tydzień (biały)
        $status_class = 'future';
 
        // Sprawdź czy tydzień jest przeszły (przed dzisiejszą datą)
        $isPast = strtotime($startDate) < strtotime(date('Y-m-d'));
 
        // Ustal kolor na podstawie godzin i daty
        if ($totalHours == 40) {
            $status_class = 'complete'; // 40/40h - Zielony
        } elseif ($totalHours > 0 && $totalHours < 40) {
            $status_class = 'partial'; // 1-39/40h - Żółty
        } elseif ($isPast && $totalHours == 0) {
            $status_class = 'missed'; // Przeszły tydzień bez godzin - Czerwony
        }
 
        echo "<div class='week-item $status_class' onclick=\"setWeekDates('$startDate', '$endDate')\">
        Tydzień " . $weekNumber . ", data od $startDate do $endDate
      </div>";
    }
 
    echo "</div>"; // Zamykanie tygodni
    echo "</div>"; // Zamykanie miesiąca
}
?>
 
</div>
 
            </div>
        </div>
    </div>
</div>
 
<!-- Modal -->
<div id="workModal" class="modal">
    <div class="modal-content">
        <span id="closeModalBtn" class="close">&times;</span>
        <h2>Wprowadź godziny pracy</h2>
        <form method="post" action="kalendarz.php">
            <input type="hidden" id="selectedWeekStart" name="selectedWeekStart">
            <input type="hidden" id="selectedWeekEnd" name="selectedWeekEnd">

            <!-- Projekt -->
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

            <!-- Tryb wprowadzenia (Tygodniowy/Dzienny) -->
            <label for="hourlyOrWeekly">Tryb wprowadzenia:</label>
            <select id="hourlyOrWeekly" name="hourlyOrWeekly" required>
                <option value="weekly">Tygodniowo</option>
                <option value="daily">Dziennie</option>
            </select><br>

            <!-- Godziny tygodniowe -->
            <div id="weeklyHoursContainer" class="hours-container">
                <label for="weeklyHours">Godziny tygodniowe:</label>
                <input type="number" id="weeklyHours" name="weeklyHours" min="0" max="<?= $max_hours_weekly ?>" step="0.5">
                <small style='font-size: 10px; color: #D4D4D4;'>Max: <?= $max_hours_weekly ?> godz.</small><br>
            </div>

            <!-- Godziny dzienne -->
            <div id="dailyHoursContainer" class="hours-container" style="display:none;">
                <?php
                $days = ['monday' => 'Pon.', 'tuesday' => 'Wt.', 'wednesday' => 'Śr.', 'thursday' => 'Czw.', 'friday' => 'Pt.'];
                foreach ($days as $day => $label) {
                    // oblicz datę konkretnego dnia tygodnia (np. Poniedziałek danego tygodnia)
                    // Na razie robimy uproszczone, klucze jako dni tygodnia
                    echo "<div class='day-input'>
                            <input type='number' id='$day' name='dailyHours[$day]' min='0' max='$max_hours_daily' step='0.5'>
                            <label for='$day'>$label</label>
                            <small style='font-size: 10px; color: #D4D4D4;'>Max: $max_hours_daily godz.</small>
                          </div>";
                }                
                ?>
            </div>

            <div style="text-align: center; margin-top: 10px;">
                <input type="submit" name="submit-hours" value="Zatwierdź" class="submit-btn">
            </div>
        </form>
    </div>
</div>

<!-- JavaScript do przełączania między trybami -->
<script>
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
</script>

 
 
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
 
function searchWeek() {
    const searchInput = document.getElementById('week-search').value.toLowerCase();
    const weeks = document.querySelectorAll('#calendar .week-item');
 
    weeks.forEach(week => {
        const text = week.textContent.toLowerCase();
        week.style.display = text.includes(searchInput) ? '' : 'none';
    });
}
 
function setWeekDates(startDate, endDate) {
    document.getElementById('selectedWeekStart').value = startDate;
    document.getElementById('selectedWeekEnd').value = endDate;
    document.getElementById('workModal').style.display = 'block';
}
 
const modal = document.getElementById('workModal');
const closeModalBtn = document.getElementById('closeModalBtn');
const hourlyOrWeekly = document.getElementById('hourlyOrWeekly');
const dailyHoursContainer = document.getElementById('dailyHoursContainer');
const weeklyHoursContainer = document.getElementById('weeklyHoursContainer');
 
closeModalBtn.addEventListener('click', () => {
    modal.style.display = 'none';
});
 
window.onclick = function(event) {
    if (event.target === modal) {
        modal.style.display = 'none';
    }
};
 
hourlyOrWeekly.addEventListener('change', () => {
    if (hourlyOrWeekly.value === 'daily') {
        dailyHoursContainer.style.display = 'flex';
        weeklyHoursContainer.style.display = 'none';
    } else {
        dailyHoursContainer.style.display = 'none';
        weeklyHoursContainer.style.display = 'block';
    }
});
</script>
 
 
 
</body>
</html>
