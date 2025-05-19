// Description: This script handles the functionality of a login form, including validation and password visibility toggle.
// Function to validate the login form
function togglePasswordVisibility() {
  const passwordInput = document.getElementById("password");
  const eyeIcon = document.getElementById("eyeIcon");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    eyeIcon.setAttribute("fill", "#007bff");
  } else {
    passwordInput.type = "password";
    eyeIcon.setAttribute("fill", "#1f1f1f");
  }
}







// theme switcher
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

// Function to set a cookie
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    
    // Switch between light and dark themes
    if (currentTheme === 'dark') {
        html.setAttribute('data-theme', 'light');  // Light theme
    } else {
        html.setAttribute('data-theme', 'dark');   // Dark theme
    }
    
    // Optional: Save the current theme to localStorage
    localStorage.setItem('theme', html.getAttribute('data-theme'));
}

// When the page loads, check for saved theme in localStorage
window.onload = function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
};

// Function to change font size
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

            

        // Function to toggle the sidebar
        function initializeSidebar() {
          const toggleSidebarBtn = document.getElementById('toggle-sidebar');
          const sidebar = document.querySelector('.sidebar');
          const sidebarOverlay = document.getElementById('sidebar-overlay');

          if (!toggleSidebarBtn || !sidebar || !sidebarOverlay) {
            console.warn('Sidebar elements not found');
            return;
          }

          const updateButtonPosition = () => {
            if (window.innerWidth > 767) {
              toggleSidebarBtn.style.left = sidebar.classList.contains('active') ? '230px' : '10px';
            } else {
              toggleSidebarBtn.style.left = '10px';
            }
          };

          toggleSidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            updateButtonPosition();
          });

          sidebarOverlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            updateButtonPosition();
          });

          window.addEventListener('resize', updateButtonPosition);
          updateButtonPosition();
        }

        // Initialize sidebar when DOM is ready
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', initializeSidebar);
        } else {
          initializeSidebar();
        }



                      // Event listeners for theme and font size toggles
                      const dateInput = document.getElementById('date');
                      if (dateInput) {
                        dateInput.addEventListener('change', function() {
                          const selectedDate = this.value;
                          const date = new Date();
                          date.setTime(date.getTime() + (60 * 60 * 1000)); // 1 godzina

                          document.cookie = `last_work_date=${selectedDate}; expires=${date.toUTCString()}; path=/`;
                        });
                      }

                        const holidays = [
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

                        document.getElementById('date').addEventListener('change', function() {
                          const selectedDate = this.value;
                          const date = new Date(selectedDate);
                          const day = date.getDay(); // 0 = Sunday, 6 = Saturday

                          if (day === 0 || day === 6 || holidays.includes(selectedDate)) {
                              alert('Nie możesz wprowadzać godzin w weekendy ani w dni świąteczne!');
                              this.value = '';
                          } else {
                              const cookieDate = new Date();
                              cookieDate.setTime(cookieDate.getTime() + (60 * 60 * 1000)); // 1 godzina
                              document.cookie = `last_work_date=${selectedDate}; expires=${cookieDate.toUTCString()}; path=/`;
                          }
                        });




                      
  





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