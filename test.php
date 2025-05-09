<?php
session_start();
require 'db.php';

$selectedYear = isset($_GET['year']) ? max(min((int)$_GET['year'], 2030), 2000) : date('Y');
$userId = $_SESSION['user_id'] ?? 1;

function getWeekHours($userId, $weekNumber, $year) {
    global $conn;
    $stmt = $conn->prepare("SELECT SUM(hours) as total FROM work_entries WHERE user_id = ? AND week_number = ? AND year = ?");
    $stmt->bind_param("iii", $userId, $weekNumber, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getStartAndEndDate($week, $year) {
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $start = $dto->format('Y-m-d');
    $dto->modify('+4 days');
    $end = $dto->format('Y-m-d');
    return [$start, $end];
}

$weeksInYear = (new DateTime("$selectedYear-12-28"))->format("W");
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Kalendarz pracy</title>
    <style>
        .week-tile {
            display: inline-block;
            width: 120px;
            height: 100px;
            margin: 5px;
            padding: 10px;
            text-align: center;
            background-color: white;
            border: 1px solid #ccc;
            cursor: pointer;
        }
        .red { background-color:rgb(225, 95, 95); }
        .yellow { background-color:rgb(223, 210, 125); }
        .green { background-color:rgb(77, 165, 89); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background: #fff; margin: 10% auto; padding: 20px; width: 500px; position: relative; }
        .close { position: absolute; top: 10px; right: 10px; cursor: pointer; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    </style>
</head>
<body>
<div style="text-align: center;">
    <a href="?year=<?= $selectedYear - 1 ?>">&laquo; <?= $selectedYear - 1 ?></a>
    <strong style="margin: 0 20px;">Rok <?= $selectedYear ?></strong>
    <a href="?year=<?= $selectedYear + 1 ?>"><?= $selectedYear + 1 ?> &raquo;</a>
</div>

<div id="calendar">
<?php
for ($week = 1; $week <= $weeksInYear; $week++) {
    list($start, $end) = getStartAndEndDate($week, $selectedYear);
    $hours = getWeekHours($userId, $week, $selectedYear);
    $color = 'white';
    if ($hours == 0) $color = 'red';
    elseif ($hours < 40) $color = 'yellow';
    else $color = 'green';
    echo "<div class='week-tile $color' onclick=\"openModal($week, '$start', '$end', $hours)\">
            <div>Tydzień $week</div>
            <div>$start - $end</div>
            <div>$hours/40h</div>
          </div>";
}
?>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3 id="modalTitle"></h3>
        <form id="workForm" method="POST">
            <input type="hidden" name="week" id="week">
            <input type="hidden" name="year" id="year" value="<?= $selectedYear ?>">
            <label>Projekt:</label>
            <select name="project_id" id="project_id"></select><br>
            <div>
                <label><input type="radio" name="entry_type" value="weekly" checked onchange="toggleEntryType()"> Godziny na cały tydzień</label><br>
                <label><input type="radio" name="entry_type" value="daily" onchange="toggleEntryType()"> Godziny dziennie</label><br>
            </div>
            <div id="weekly_hours">
                <label>Godziny na cały tydzień:</label>
                <input type="number" name="week_hours" id="week_hours" min="0" max="40" oninput="updateIndicator()"><br>
            </div>
            <div id="daily_hours" style="display: none;">
                <label>Poniedziałek:</label><input type="number" name="hours[Mon]" min="0" max="8" oninput="updateIndicator()"><br>
                <label>Wtorek:</label><input type="number" name="hours[Tue]" min="0" max="8" oninput="updateIndicator()"><br>
                <label>Środa:</label><input type="number" name="hours[Wed]" min="0" max="8" oninput="updateIndicator()"><br>
                <label>Czwartek:</label><input type="number" name="hours[Thu]" min="0" max="8" oninput="updateIndicator()"><br>
                <label>Piątek:</label><input type="number" name="hours[Fri]" min="0" max="8" oninput="updateIndicator()"><br>
            </div>
            <p>Wpisane godziny: <span id="total-hours">0/40h</span></p>
            <button type="submit">Zapisz</button>
        </form>

        <!-- Tabela z godzinami i projektami -->
        <div id="summary">
            <h4>Podsumowanie godzin w projekcie:</h4>
            <table>
                <thead>
                    <tr>
                        <th>Projekt</th>
                        <th>Dzień</th>
                        <th>Godziny</th>
                    </tr>
                </thead>
                <tbody id="hours-table-body">
                    <!-- Tabela będzie uzupełniana danymi z funkcji JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Otwieranie modalu
function openModal(week, start, end, hours) {
    document.getElementById('modalTitle').innerText = `Tydzień ${week} (${start} - ${end})`;
    document.getElementById('week').value = week;
    document.getElementById('modal').style.display = 'block';
    loadProjects();
    loadSummary(week);
    updateIndicator(hours);
    loadSavedHours(week);
}

// Zamknięcie modalu
function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

// Załadowanie projektów do selecta
function loadProjects() {
    fetch('get_projects.php')
        .then(r => r.json())
        .then(data => {
            let select = document.getElementById('project_id');
            select.innerHTML = '';
            data.forEach(p => {
                let opt = document.createElement('option');
                opt.value = p.id;
                opt.innerText = p.name;
                select.appendChild(opt);
            });
        });
}

// Załadowanie podsumowania godzin dla tygodnia
function loadSummary(week) {
    fetch(`get_week_data.php?week=${week}&year=${<?= $selectedYear ?>}`)
        .then(r => r.json())
        .then(data => {
            let tableBody = document.getElementById('hours-table-body');
            tableBody.innerHTML = ''; // Czyścimy tabelę przed dodaniem nowych danych

            for (let entry of data) {
                let row = document.createElement('tr');
                row.innerHTML = `<td>${entry.project}</td><td>${entry.day}</td><td>${entry.hours}h</td>`;
                tableBody.appendChild(row);
            }
        });
}

// Zmiana typu wpisu na tygodniowy lub dzienny
function toggleEntryType() {
    const entryType = document.querySelector('input[name="entry_type"]:checked').value;
    if (entryType === 'weekly') {
        document.getElementById('weekly_hours').style.display = 'block';
        document.getElementById('daily_hours').style.display = 'none';
    } else {
        document.getElementById('weekly_hours').style.display = 'none';
        document.getElementById('daily_hours').style.display = 'block';
    }
}

// Funkcja do aktualizacji wskaźnika godzin
function updateIndicator(hours = 0) {
    let totalHours = 0;
    if (document.querySelector('input[name="entry_type"]:checked').value === 'weekly') {
        totalHours = parseInt(document.getElementById('week_hours').value) || 0;
    } else {
        const dailyInputs = document.querySelectorAll('input[name^="hours"]');
        dailyInputs.forEach(input => {
            totalHours += parseInt(input.value) || 0;
        });
    }

    document.getElementById('total-hours').innerText = `${totalHours}/40h`;

    const totalHoursElement = document.getElementById('total-hours');
    if (totalHours === 0) {
        totalHoursElement.style.color = 'red';
    } else if (totalHours < 40) {
        totalHoursElement.style.color = 'yellow';
    } else {
        totalHoursElement.style.color = 'green';
    }
}

// Ładowanie zapisanych godzin z localStorage
function loadSavedHours(week) {
    let savedHours = JSON.parse(localStorage.getItem(`week_${week}_hours`)) || {};
    if (savedHours) {
        if (savedHours.type === 'weekly') {
            document.getElementById('week_hours').value = savedHours.hours || 0;
        } else {
            for (let day in savedHours.hours) {
                document.querySelector(`input[name="hours[${day}]"]`).value = savedHours.hours[day];
            }
        }
    }
}

document.getElementById('workForm').addEventListener('submit', function (event) {
    event.preventDefault();
    let week = document.getElementById('week').value;
    let entryType = document.querySelector('input[name="entry_type"]:checked').value;
    saveHours(week, entryType);
});
</script>
</body>
</html>
