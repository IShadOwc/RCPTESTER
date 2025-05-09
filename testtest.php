<?php
session_start();
require 'db.php';

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

function getExistingHours($userId, $weekStart) {
    global $conn;
    $dates = [];
    $baseDate = new DateTime($weekStart);
    for ($i = 0; $i < 5; $i++) {
        $dates[] = $baseDate->format('Y-m-d');
        $baseDate->modify('+1 day');
    }

    $placeholders = implode(',', array_fill(0, count($dates), '?'));
    $types = str_repeat('s', count($dates));
    $params = array_merge([$userId], $dates);

    $stmt = $conn->prepare("SELECT date, hours FROM work_entries WHERE user_id = ? AND date IN ($placeholders)");
    $stmt->bind_param("s" . $types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $hours = [];
    while ($row = $result->fetch_assoc()) {
        $hours[$row['date']] = $row['hours'];
    }

    return $hours;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_hours']) && $_POST['load_hours'] == '1') {
    $project_id = $_POST['project_id'];
    $total_hours = 0;

    // Sprawdzanie, czy projekt ma przypisaną nazwę, jeśli nie, przypisujemy domyślną
    $project_name = isset($_POST['project_name']) ? $_POST['project_name'] : 'Brak nazwy projektu';

    // Uzupełnienie brakujących zmiennych (np. user_id, username) - przykładowo
    $user_id = $_SESSION['user_id'];  // Przykład, możesz to dostosować
    $username = $_SESSION['username']; // Przykład, możesz to dostosować
    $created_at = date('Y-m-d H:i:s');  // Aktualny czas, jeśli chcesz go zapisać
    $week_number = date('W');  // Możesz użyć np. numeru tygodnia bieżącego
    $year = date('Y');  // Bieżący rok

    // Przechowywanie danych w sesji przed przekierowaniem
    $_SESSION['week_start'] = $_POST['week_start'];
    $_SESSION['week_end'] = $_POST['week_end'];
    $_SESSION['project_id'] = $project_id;
    $_SESSION['hours'] = $_POST['hours'];

    // Zapis danych do bazy
    $conn->begin_transaction();
    try {
        foreach ($_POST['hours'] as $day => $hours) {
            if (!empty($hours)) {
                $date = $_POST['week_start'];
                $entry_date = date('Y-m-d', strtotime($date . " +$day days"));
                $hours = floatval($hours);
                $total_hours += $hours;

                // Zaktualizowane zapytanie SQL
                $stmt = $conn->prepare("INSERT INTO work_entries 
                    (user_id, username, project_id, project_name, date, hours, created_at, week_number, year) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isissdsii", 
                    $user_id, 
                    $username, 
                    $project_id, 
                    $project_name, 
                    $entry_date, 
                    $hours, 
                    $created_at, 
                    $week_number, 
                    $year
                );
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->commit();
        echo "<script>alert('Dane zostały zapisane pomyślnie.');</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Błąd zapisu: " . $e->getMessage() . "');</script>";
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?section=manage_user");
    exit;
}


if (isset($_GET['week_start']) && isset($_GET['week_end'])) {
    $weekStart = $_GET['week_start'];
    $weekEnd = $_GET['week_end'];
    $userId = $_SESSION['user_id'] ?? 1;

    $hours = getExistingHours($userId, $weekStart);

    echo json_encode($hours);
    exit();
}

function getHoursForWeek($weekStart, $weekEnd, $projectId) {
    global $conn;
    $stmt = $conn->prepare("SELECT SUM(hours) as total_hours FROM work_entries WHERE project_id = ? AND date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $projectId, $weekStart, $weekEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return (float) $row['total_hours'];
}

// Pobieramy godziny pracy z bazy dla danego tygodnia i projektu
if (isset($weekStartFormatted) && isset($weekEndFormatted) && isset($projectId)) {
    $query = "SELECT * FROM work_entries WHERE week_start = '$weekStartFormatted' AND week_end = '$weekEndFormatted' AND project_id = $projectId";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $entry = mysqli_fetch_assoc($result);

        // Przypisujemy godziny do zmiennych dla każdego dnia
        $monday_hours = $entry['monday_hours'];
        $tuesday_hours = $entry['tuesday_hours'];
        $wednesday_hours = $entry['wednesday_hours'];
        $thursday_hours = $entry['thursday_hours'];
        $friday_hours = $entry['friday_hours'];
    } else {
        // Jeśli brak danych, ustawiamy godziny na 0 (lub na jakąś wartość domyślną)
        $monday_hours = 0;
        $tuesday_hours = 0;
        $wednesday_hours = 0;
        $thursday_hours = 0;
        $friday_hours = 0;
    }
}

// Zapytanie SQL, aby pobrać wszystkie projekty
$project_query = "SELECT id, name FROM projects";
$project_result = $conn->query($project_query);

$projects = [];
if ($project_result && $project_result->num_rows > 0) {
    while ($project_row = $project_result->fetch_assoc()) {
        $projects[] = $project_row;
    }
}

if ($project_result && $project_result->num_rows > 0) {
    // Przechodzimy po wynikach i wyświetlamy projekty
    while ($project_row = $project_result->fetch_assoc()) {
        $project_id = $project_row['id'];
        $project_name = $project_row['name'];

        // Możesz teraz wyświetlić projekt w formie listy lub innych elementów HTML
        echo "<option value=\"$project_id\">$project_name</option>";
    }
} else {
    echo "<p>Brak dostępnych projektów.</p>";
}


$user_id = $_SESSION['user_id']; // lub jak to masz u siebie

$sql = "SELECT * FROM work_entries 
        WHERE user_id = ? 
        AND date BETWEEN ? AND ?
        ORDER BY date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $user_id, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start']) && isset($_POST['end'])) {
    $startDate = $_POST['start'];
    $endDate = $_POST['end'];
    // kontynuuj tylko jeśli dane są faktycznie przesłane
}


?>


<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wpisy godzin pracy</title>
    <style>
        .week-tile { display: inline-block; width: 100px; height: 100px; background-color: lightgray; margin: 5px; text-align: center; cursor: pointer; }
        .week-tile span { display: block; }
        .week-tile.red { background-color: red; }
        .week-tile.yellow { background-color: yellow; }
        .week-tile.green { background-color: green; }
        .week-tile.white { background-color: white; }
        #form-container { margin-top: 30px; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); padding-top: 60px; }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
    </style>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div style="text-align: center; margin-bottom: 20px;">
    <a href="?year=<?= $selectedYear - 1 ?>">&larr; Poprzedni rok</a>
    <strong style="margin: 0 20px;">Rok <?= $selectedYear ?></strong>
    <a href="?year=<?= $selectedYear + 1 ?>">Następny rok &rarr;</a>
</div>

<div id="weeks-container">
    <?php
    $startDate = new DateTime("first monday of January $selectedYear");
    $endDate = new DateTime("last sunday of December $selectedYear");
    
    $currentDate = $startDate;

    while ($currentDate <= $endDate) {
        $weekStart = clone $currentDate;
        $weekEnd = clone $currentDate;
        $weekEnd->modify('sunday this week');

        $weekStartFormatted = $weekStart->format('Y-m-d');
        $weekEndFormatted = $weekEnd->format('Y-m-d');

        $projectId = 1;
        $hours = getHoursForWeek($weekStartFormatted, $weekEndFormatted, $projectId);

        $colorClass = 'white'; 

        if ($hours == 0) $colorClass = 'red';
        elseif ($hours < 40) $colorClass = 'yellow';
        elseif ($hours >= 40) $colorClass = 'green';
        else $colorClass = 'white';




        echo "<div class='week-tile $colorClass' onclick='openModal(\"$weekStartFormatted\", \"$weekEndFormatted\")'>
        <span>Tydzień {$currentDate->format("W")}</span>
        <span>{$weekStart->format('d.m')} - {$weekEnd->format('d.m')}</span>
        <span>{$hours}/40h</span>
    </div>";

    $currentDate->modify('+1 week');
}
?>

<!-- Modal formularza -->
<!-- MODAL -->
<div id="myModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2 id="modalTitle">Wprowadź godziny pracy</h2>

    <form method="POST" action="testtest.php">
      <input type="hidden" name="load_hours" value="1">
      <input type="hidden" id="week_start" name="week_start" value="">
      <input type="hidden" id="week_end" name="week_end" value="">

      <label for="project_id">Projekt:</label>
      <select name="project_id" id="project_id" required onchange="updateProjectName()">
        <option value="">-- Wybierz projekt --</option>
        <?php foreach ($projects as $project): ?>
          <option value="<?= $project['id'] ?>" data-name="<?= htmlspecialchars($project['name']) ?>"><?= htmlspecialchars($project['name']) ?></option>
        <?php endforeach; ?>
      </select><br><br>

      <!-- Ukryte pole do przechowywania nazwy projektu -->
      <input type="hidden" name="project_name" id="project_name">

      <select id="hourlyOrWeekly" onchange="toggleInputMode()">
        <option value="daily">Wpis dzienny</option>
        <option value="weekly">Wpis tygodniowy</option>
      </select>

      <div id="hours-inputs">
        <?php
        $days = ['Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek'];
        foreach ($days as $index => $day):
        ?>
          <label><?= $day ?>:</label>
          <input type="number" id="<?= strtolower($day) ?>_hours" name="hours[<?= $index ?>]" step="0.1" min="0"><br>
        <?php endforeach; ?>
      </div>

      <div id="hourSummary">0/40h</div>

      <br><input type="submit" value="Zapisz godziny">
    </form>

    <h3>Wpisy z tego tygodnia:</h3>
    <table border="1" style="width:100%; margin-top:10px;">
      <thead>
        <?php
          while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['date']}</td>
                    <td>{$row['project_name']}</td>
                    <td>{$row['hours']}</td>
                  </tr>";
          }
        ?>
      </thead>
      <tbody>
        <?php
        $selectedStart = $_POST['selectedWeekStart'] ?? null;
        if ($selectedStart) {
          $query = $conn->prepare("SELECT we.date, p.name as project, we.hours FROM work_entries we JOIN projects p ON we.project_id = p.id WHERE user_id = ? AND we.date BETWEEN ? AND DATE_ADD(?, INTERVAL 4 DAY)");
          $query->bind_param("iss", $user_id, $selectedStart, $selectedStart);
          $query->execute();
          $result = $query->get_result();

          while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['date']}</td><td>" . htmlspecialchars($row['project']) . "</td><td>{$row['hours']}</td></tr>";
          }
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Funkcja do aktualizacji nazwy projektu w ukrytym polu
  function updateProjectName() {
    var projectSelect = document.getElementById('project_id');
    var projectName = projectSelect.options[projectSelect.selectedIndex].getAttribute('data-name');
    document.getElementById('project_name').value = projectName;
  }
</script>




<script>
    var modal = document.getElementById('myModal');
    var closeBtn = document.getElementsByClassName('close')[0];

const dailyInputs = document.querySelectorAll('#dailyHoursContainer input[type="number"]');
const weeklyInput = document.getElementById('weeklyHours');
const liveSummary = document.getElementById('liveSummary');

function updateLiveSummary() {
    const mode = document.getElementById('hourlyOrWeekly').value;
    let totalHours = 0;

    if (mode === 'daily') {
        document.querySelectorAll('.dailyInput').forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) totalHours += val;
        });
    } else if (mode === 'weekly') {
        const val = parseFloat(document.getElementById('weeklyInput').value);
        if (!isNaN(val)) totalHours = val;
    }

    // Ustaw kolor i tekst
    const summary = document.getElementById('hourSummary');
    summary.textContent = `${totalHours}/40h`;

    if (totalHours === 0) {
        summary.style.color = 'red';
    } else if (totalHours < 40) {
        summary.style.color = 'orange';
    } else {
        summary.style.color = 'green';
    }
}


dailyInputs.forEach(input => input.addEventListener('input', updateLiveSummary));
weeklyInput.addEventListener('input', updateLiveSummary);
document.getElementById('hourlyOrWeekly').addEventListener('change', updateLiveSummary);

function openModal(startDateStr, endDateStr) {
    const startDate = new Date(startDateStr);
    const endDate = new Date(endDateStr);

    const weekNumber = getWeekNumber(startDate); // Musisz mieć taką funkcję
    const formattedStart = startDate.toLocaleDateString('pl-PL');
    const formattedEnd = endDate.toLocaleDateString('pl-PL');

    document.getElementById('modalTitle').textContent = `Tydzień ${weekNumber} (${formattedStart} - ${formattedEnd})`;
    modal.style.display = 'block';

}



    document.querySelectorAll('.week-tile').forEach(function(tile) {
        tile.onclick = function() {
            var weekStart = this.getAttribute('data-week-start');
            var weekEnd = this.getAttribute('data-week-end');
            document.getElementById('week_start').value = weekStart;
            document.getElementById('week_end').value = weekEnd;
            document.getElementById('modal-week-info').innerText = "Wpisz godziny dla tygodnia: " + weekStart + " - " + weekEnd;
            modal.style.display = "block";
        };
    });

    closeBtn.onclick = function() {
        modal.style.display = "none";
    };
document.querySelectorAll('.week-tile').forEach(tile => {
    tile.addEventListener('click', () => {
        const weekStart = tile.getAttribute('data-week-start');
        const weekEnd = tile.getAttribute('data-week-end');

        document.getElementById('modal-week-info').innerText = `Tydzień ${tile.querySelector('span').innerText} (${weekStart} - ${weekEnd})`;
        document.getElementById('week_start').value = weekStart;
        document.getElementById('week_end').value = weekEnd;

        // Wyczyść wszystkie pola godzin
        ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].forEach(day => {
            document.getElementById(`${day}_hours`).value = '';
        });

        document.getElementById('summary').innerText = '0/40 godzin';

        // Załaduj godziny z bazy danych (wszystko w tym samym pliku)
        fetchHours(weekStart, weekEnd);
        
        document.getElementById('myModal').style.display = "block";
    });
});

// Funkcja ładowania godzin z bazy danych
function fetchHours(weekStart, weekEnd) {
    const userId = 1; // ID użytkownika, możesz dynamicznie pobrać z sesji
    const url = `?week_start=${weekStart}&week_end=${weekEnd}&user_id=${userId}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            // Załaduj dane godzin do formularza
            ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].forEach(day => {
                if (data[day]) {
                    document.getElementById(`${day}_hours`).value = data[day];
                }
            });

            // Aktualizuj podsumowanie
            const totalHours = Object.values(data).reduce((sum, hours) => sum + parseFloat(hours), 0);
            document.getElementById('summary').innerText = `${totalHours}/40 godzin`;

            if (totalHours === 0) {
                document.getElementById('summary').style.color = 'red';
            } else if (totalHours < 40) {
                document.getElementById('summary').style.color = 'orange';
            } else if (totalHours === 40) {
                document.getElementById('summary').style.color = 'green';
            }
        })
        .catch(error => console.error('Błąd podczas ładowania godzin:', error));
}

// Podsumowanie godzin w czasie rzeczywistym
const inputs = document.querySelectorAll('#hours-inputs input[type="number"]');
inputs.forEach(input => {
    input.addEventListener('input', () => {
        let total = 0;
        inputs.forEach(i => {
            const val = parseFloat(i.value);
            if (!isNaN(val)) total += val;
        });

        const summary = document.getElementById('summary');
        summary.innerText = `${total}/40 godzin`;

        if (total === 0) {
            summary.style.color = 'red';
        } else if (total < 40) {
            summary.style.color = 'orange';
        } else if (total === 40) {
            summary.style.color = 'green';
        } else {
            summary.style.color = 'black';
        }
    });
});

document.getElementById('hourlyOrWeekly').addEventListener('change', function() {
    if (this.value === 'weekly') {
        document.getElementById('weeklyHoursContainer').style.display = 'block';
        document.getElementById('dailyHoursContainer').style.display = 'none';
    } else {
        document.getElementById('weeklyHoursContainer').style.display = 'none';
        document.getElementById('dailyHoursContainer').style.display = 'block';
    }
});

function getWeekNumber(date) {
    const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
    const dayNum = d.getUTCDay() || 7;
    d.setUTCDate(d.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    return Math.ceil((((d - yearStart) / 86400000) + 1)/7);
}

function toggleInputMode() {
    const mode = document.getElementById('hourlyOrWeekly').value;
    document.getElementById('dailyInputsContainer').style.display = mode === 'daily' ? 'block' : 'none';
    document.getElementById('weeklyInputContainer').style.display = mode === 'weekly' ? 'block' : 'none';
    updateLiveSummary();
}
window.addEventListener('DOMContentLoaded', () => {
    toggleInputMode();
});

document.querySelector('.close').addEventListener('click', () => {
    document.getElementById('workModal').style.display = 'none';
});

function openModal(weekStart, weekEnd) {
    const modal = document.getElementById("myModal");
    document.getElementById("week_start").value = weekStart;
    document.getElementById("week_end").value = weekEnd;
    modal.style.display = "block";
}

// Zamykanie modala po kliknięciu w "x"
document.querySelector(".close").onclick = function() {
    document.getElementById("myModal").style.display = "none";
}

// Zamykanie modala po kliknięciu poza treścią
window.onclick = function(event) {
    const modal = document.getElementById("myModal");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("myModal");
    const closeBtn = modal.querySelector(".close");

    // Otwieranie modala
    document.getElementById("openModalBtn").addEventListener("click", function () {
        modal.style.display = "block";
    });

    // Zamknięcie przy kliknięciu X
    closeBtn.addEventListener("click", function () {
        modal.style.display = "none";
    });

    // Zamknięcie przy kliknięciu poza modalem
    window.addEventListener("click", function (event) {
        if (event.target === modal) {
            modal.style.display = "none";
        }
    });
});


// Modal
var modal = document.getElementById('myModal');
var closeBtn = document.getElementsByClassName('close')[0];

// Open modal (this should already work as it's called by the onclick in HTML)
function openModal(weekStart, weekEnd) {
    document.getElementById('week_start').value = weekStart;
    document.getElementById('week_end').value = weekEnd;
    modal.style.display = "block";  // Show modal
}

// Close modal when 'X' button is clicked
closeBtn.onclick = function() {
    modal.style.display = "none";  // Hide modal
}

// Close modal if the user clicks outside of the modal
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";  // Hide modal if the click was outside the modal
    }
}


</script>

</body>
</html>
