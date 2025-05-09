<?php
require __DIR__ . '/vendor/autoload.php'; // Załaduj PHPMailera

// Połączenie z bazą danych
require 'db.php'; // Twoje połączenie z bazą danych

$max_hours_per_day = 8;
$max_hours_per_week = 40;

$weekStart = date('Y-m-d', strtotime('last Monday'));
$weekEnd = date('Y-m-d', strtotime('Sunday'));

$query = "
    SELECT u.id, u.name, u.email 
    FROM users u
    LEFT JOIN work_entries we ON u.id = we.user_id AND we.week_date BETWEEN '$weekStart' AND '$weekEnd'
    GROUP BY u.id
";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $query_hours = "
            SELECT SUM(hours) AS total_hours
            FROM work_entries
            WHERE user_id = '$user_id' AND week_date BETWEEN '$weekStart' AND '$weekEnd'
        ";
        $hours_result = $conn->query($query_hours);
        $hours_row = $hours_result->fetch_assoc();
        $total_hours = $hours_row['total_hours'] ?? 0;

        if ($total_hours < $max_hours_per_week) {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            $mail->isMail(); // Użyj funkcji mail(), nie SMTP

            $mail->setFrom('noreply@twojastrona.pl', 'Zespół ETA Gliwice');
            $mail->addAddress($row['email']);
            $mail->Subject = 'Brakujące godziny pracy w raporcie - przypomnienie';

            $mailContent = "
            <html>
            <body>
            <p>Witaj <strong>{$row['name']}</strong>,</p>
            <p>Chcielibyśmy przypomnieć, że w Twoim raporcie czasu pracy na ten tydzień brakuje godzin. Aby Twój raport został uznany za kompletny, prosimy o uzupełnienie brakujących godzin w systemie.</p>
            <p>Pamiętaj, że na koniec tygodnia wymagane jest wprowadzenie godzin za każdy dzień roboczy, aby Twój raport mógł zostać zatwierdzony.</p>
            <p><span style='color:red;'>Jeśli masz jakiekolwiek pytania lub potrzebujesz pomocy, nie wahaj się skontaktować.</span></p>
            <p>Do uzupełnienia godzin: <a href='http://twojastrona.pl/rcp' style='color:blue;'>http://twojastrona.pl/rcp</a></p>
            <p><strong>Dziękujemy za Twoją współpracę.</strong></p>
            <p><strong>Z pozdrowieniami,</strong><br>Zespół ETA Gliwice</p>
            <p>Miłego weekendu!</p>
            <p><img src='ETA-logo.jpg' alt='Logo ETA Gliwice' width='200'></p>
            </body>
            </html>";

            $mail->isHTML(true);
            $mail->Body = $mailContent;

            if (!$mail->send()) {
                echo 'Błąd wysyłki do ' . $row['email'] . ': ' . $mail->ErrorInfo . '<br>';
            } else {
                echo 'Wysłano przypomnienie do: ' . $row['email'] . '<br>';
            }

            $mail->clearAddresses();
        }
    }
}

$conn->close();
?>
