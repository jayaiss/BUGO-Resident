document.addEventListener('DOMContentLoaded', function () {
  const calendar = document.getElementById('calendar');
  const monthSelect = document.getElementById('monthSelect');
  const yearSelect = document.getElementById('yearSelect');
  const prevMonth = document.getElementById('prevMonth');
  const nextMonth = document.getElementById('nextMonth');
  const timeSlotModal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
  const formModal = new bootstrap.Modal(document.getElementById('formModal'));
  const timeSlotDateDisplay = document.getElementById('timeSlotDateDisplay');
  const selectedDateTimeDisplay = document.getElementById('selectedDateTimeDisplay');
  const timeSlotsContainer = document.getElementById('timeSlots');
  const appointmentDateTimeInput = document.getElementById('appointmentDateTime');

  let selectedDayElement = null;
  let currentSelectedDate = null;
  let currentDate = new Date();
  let holidays = [];

  function populateMonthYearDropdowns() {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    monthSelect.innerHTML = '';
    monthNames.forEach((name, index) => {
      const opt = document.createElement('option');
      opt.value = index;
      opt.textContent = name;
      if (index === currentDate.getMonth()) opt.selected = true;
      monthSelect.appendChild(opt);
    });

    const year = new Date().getFullYear();
    yearSelect.innerHTML = '';
    for (let y = year - 3; y <= year + 5; y++) {
      const opt = document.createElement('option');
      opt.value = y;
      opt.textContent = y;
      if (y === year) opt.selected = true;
      yearSelect.appendChild(opt);
    }
  }

function renderCalendar() {
  calendar.innerHTML = '';
  const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
  const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
  const startDay = firstDay.getDay();
  const totalDays = lastDay.getDate();
  const today = new Date();
  today.setHours(0, 0, 0, 0); // Normalize time to compare dates

  for (let i = 0; i < startDay; i++) {
    calendar.appendChild(document.createElement('div'));
  }

  for (let day = 1; day <= totalDays; day++) {
    const date = new Date(currentDate.getFullYear(), currentDate.getMonth(), day);
    date.setHours(0, 0, 0, 0); // Normalize date

    const formattedDate = date.toLocaleDateString('en-CA');
    const dayElement = document.createElement('div');
    dayElement.classList.add('day');
    dayElement.textContent = day;

    const isWeekend = [0,6].includes(date.getDay());
    const isHoliday = holidays.find(h => h.date === formattedDate);
    const isPast = date < today;

    if (isWeekend) dayElement.classList.add('weekend');
    if (isHoliday) {
      dayElement.classList.add('holiday');
      dayElement.title = isHoliday.name;
    }

    if (isPast) {
      dayElement.classList.add('disabled-date');
    } else {
      // Only clickable for today or future
      dayElement.addEventListener('click', () => {
        if (selectedDayElement) selectedDayElement.classList.remove('selected-day');
        dayElement.classList.add('selected-day');
        selectedDayElement = dayElement;

        currentSelectedDate = formattedDate;
        timeSlotDateDisplay.textContent = formattedDate;

        renderTimeSlots();
        timeSlotModal.show();
      });
    }

    if (date.toDateString() === today.toDateString()) {
      dayElement.classList.add('current-day');
    }

    calendar.appendChild(dayElement);
  }
}



function renderTimeSlots() {
  timeSlotsContainer.innerHTML = '';
  if (!currentSelectedDate) return;

  fetch(`ajax/fetch_time_slots.php?date=${currentSelectedDate}`)
    .then(res => res.json())
    .then(slots => {
      if (!Array.isArray(slots) || slots.length === 0) {
        timeSlotsContainer.innerHTML = '<div class="text-danger">No available time slots.</div>';
        return;
      }

      const now = new Date();
       // ✅ Sort slots by start time
      const parseSlotStartTime = (label) => {
        const [startRaw] = label.split('-').map(s => s.trim());
        const isPM = startRaw.toUpperCase().includes('PM');
        let [hour, minute] = startRaw.replace(/AM|PM/i, '').split(':').map(Number);
        if (isPM && hour < 12) hour += 12;
        if (!isPM && hour === 12) hour = 0;
        return hour * 60 + (minute || 0);
      };

      slots.sort((a, b) => parseSlotStartTime(a.label) - parseSlotStartTime(b.label));

      slots.forEach(slot => {
        const label = slot.label; // e.g., "09:00AM-10:00AM"
        const [startRaw, endRaw] = label.split('-').map(s => s.trim());

        const parseTime = (timeStr) => {
          const isPM = timeStr.toUpperCase().includes('PM');
          let [hour, minute] = timeStr.replace(/AM|PM/i, '').split(':').map(Number);
          if (isPM && hour < 12) hour += 12;
          if (!isPM && hour === 12) hour = 0;
          return { hour, minute: minute || 0 };
        };

        const { hour: startHour, minute: startMinute } = parseTime(startRaw);

        const slotStart = new Date(currentSelectedDate);
        slotStart.setHours(startHour, startMinute, 0, 0);

        const btn = document.createElement('button');
        btn.classList.add('btn', 'time-slot', 'w-100', 'mb-2');
        btn.setAttribute('data-time', label);

        const booked = parseInt(slot.booked || 0);
        const limit = parseInt(slot.limit || 10);

        const isPastSlot = slotStart < now;

        if (isPastSlot) {
          btn.classList.add('btn-secondary');
          btn.disabled = true;
          btn.textContent = `${label} (Closed)`;
        } else if (booked >= limit) {
          btn.classList.add('btn-danger');
          btn.disabled = true;
          btn.textContent = `${label} (${booked}/${limit} FULL)`;
        } else {
          btn.classList.add('btn-success');
          btn.textContent = `${label} (${booked}/${limit} booked)`;
          btn.addEventListener('click', () => {
            const selectedDateTime = `${currentSelectedDate} ${label}`;
            selectedDateTimeDisplay.textContent = selectedDateTime;
            appointmentDateTimeInput.value = selectedDateTime;

            timeSlotModal.hide();
            formModal.show();

            setTimeout(() => {
              document.getElementById('income')?.focus();
            }, 300);
          });
        }

        timeSlotsContainer.appendChild(btn);
      });
    })
    .catch(err => {
      console.error("❌ Failed to fetch slots:", err);
      timeSlotsContainer.innerHTML = '<div class="text-danger">Error loading slots.</div>';
    });
}



  // Navigation handlers
  prevMonth?.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    syncDropdowns();
    renderCalendar();
  });

  nextMonth?.addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    syncDropdowns();
    renderCalendar();
  });

  monthSelect?.addEventListener('change', () => {
    currentDate.setMonth(parseInt(monthSelect.value));
    renderCalendar();
  });

  yearSelect?.addEventListener('change', () => {
    currentDate.setFullYear(parseInt(yearSelect.value));
    renderCalendar();
  });

  function syncDropdowns() {
    monthSelect.value = currentDate.getMonth();
    yearSelect.value = currentDate.getFullYear();
  }

  // Fetch holidays then render calendar
  fetch('ajax/fetch_holidays.php')
    .then(res => res.json())
    .then(data => {
      holidays = data;
      populateMonthYearDropdowns();
      renderCalendar();
    })
    .catch(err => {
      console.error('❌ Failed to load holidays:', err);
      populateMonthYearDropdowns();
      renderCalendar();
    });
});
