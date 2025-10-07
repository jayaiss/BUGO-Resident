(function injectFullyBookedStyle () {
  const style = document.createElement('style');
  style.textContent = `
    .day.fully-booked {
      background-color: #ffe5e5 !important;
      border: 1px solid #cc0000 !important;
      color: #cc0000 !important;
      cursor: not-allowed;
    }
    .day .fully-label {
      font-size: .7rem;
      font-weight: 600;
      margin-top: .15rem;
      display: block;
    }
  `;
  document.head.appendChild(style);
})();

const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
let times = [];
let holidays = [];
let bulkFullyBooked = []; // Will store fully booked days for the month
let currentDate = new Date();
let selectedDate = null;
let hasSelectedDate = false;
// --- slot helpers (canonicalize, sort, label) ---
function parseHHMMSS(t){ const [H='0',M='0']=String(t||'').split(':'); return {h:+H||0,m:+M||0}; }
function fmt12(h24, m=0, padHour=false){
  const ampm = h24 >= 12 ? 'PM' : 'AM';
  let h = h24 % 12; if (h === 0) h = 12;
  const hStr = padHour ? String(h).padStart(2,'0') : String(h);
  const mStr = String(m).padStart(2,'0');
  return `${hStr}:${mStr}${ampm}`;
}

// Normalize API payload -> array of unique, sorted slots
function normalizeSlots(raw){
  const list = Array.isArray(raw) ? raw : (raw?.timeSlots || []);
  // Build canonical key and display label
  const uniq = new Map();
  for (const s of list){
    const start = s.start_time;     // "HH:MM:SS" if backend updated
    const end   = s.end_time;
    const key   = (start && end) ? `${start}-${end}` : s.label;

    // compute display label (prefer canonical times)
    let label;
    if (start && end){
      const S = parseHHMMSS(start);
      const E = parseHHMMSS(end);
      // start without leading zero, end with leading zero (as requested)
      label = `${fmt12(S.h, S.m, false)}-${fmt12(E.h, E.m, true)}`;
    } else {
      label = s.label;
    }

    // unify fields; prefer slot_limit, fall back to limit
    const limit = (s.slot_limit ?? s.limit ?? 0) | 0;
    const booked = (s.booked ?? 0) | 0;

    const canon = {
      key,
      start_time: start || null,
      end_time: end || null,
      label,
      limit,
      booked
    };

    if (!uniq.has(key)) uniq.set(key, canon);
  }
  const arr = [...uniq.values()];
  // sort by canonical start_time (fallback: by label)
  arr.sort((a,b)=>{
    const sa = a.start_time || a.label;
    const sb = b.start_time || b.label;
    return String(sa).localeCompare(String(sb));
  });
  return arr;
}


// ---- Debounce Helper ----
function debounce(func, wait) {
  let timeout;
  return function(...args) {
    const later = () => {
      clearTimeout(timeout);
      func.apply(this, args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}
const debouncedFetchMap = {}; // One debouncer per date

// ---- Fetch holidays and bulk fully booked dates ----
fetch('ajax/fetch_holidays.php')
  .then(response => response.json())
  .then(data => {
    holidays = data;
    processFullyBookedDates();
    populateYearDropdown(currentDate.getFullYear());
    fetchBulkFullyBooked(getMonthStr(currentDate), generateCalendar); // Fetch and THEN render
  })
  .catch(error => {
    console.error('❌ Failed to load holidays:', error);
    populateYearDropdown(currentDate.getFullYear());
    fetchBulkFullyBooked(getMonthStr(currentDate), generateCalendar);
  });

function getMonthStr(date) {
  return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
}

function fetchBulkFullyBooked(monthStr, cb) {
  // Adjust the endpoint below if you use a different PHP filename
  fetch(`ajax/fetch_fully_booked_days.php?month=${monthStr}`)
    .then(res => res.json())
    .then(data => {
      bulkFullyBooked = data.fullyBookedDays || [];
      if (typeof cb === 'function') cb();
    })
    .catch(() => {
      bulkFullyBooked = [];
      if (typeof cb === 'function') cb();
    });
}

function processFullyBookedDates() {
  holidays.forEach(holiday => {
    if (holiday.name === 'Fully Booked') {
      holiday.isFullyBooked = true;
    }
  });
}

function generateCalendar() {
  const month = currentDate.getMonth();
  const year = currentDate.getFullYear();
  const firstDayOfMonth = new Date(year, month, 1);
  const lastDayOfMonth = new Date(year, month + 1, 0);

  document.getElementById('calendarHeader').textContent = formatMonthYear();

  let calendarHTML = '';

  // Header (Sun–Sat)
  daysOfWeek.forEach(day => {
    calendarHTML += `<div class="day bg-light py-3 fw-bold">${day}</div>`;
  });

  for (let i = 0; i < firstDayOfMonth.getDay(); i++) {
    calendarHTML += `<div class="day"></div>`;
  }

  const today = new Date();
  const todayStr = formatLocalDate(today);

  for (let day = 1; day <= lastDayOfMonth.getDate(); day++) {
    const date = new Date(year, month, day);
    const dateFormatted = formatLocalDate(date);
    const dateToCheck = new Date(year, month, day);
    today.setHours(0, 0, 0, 0);
    dateToCheck.setHours(0, 0, 0, 0);

    const isWeekend = date.getDay() === 0 || date.getDay() === 6;
    const isPast = dateToCheck < today;
    const isToday = dateFormatted === todayStr;

    const holiday = holidays.find(h => h.date === dateFormatted);
    const isHoliday = !!holiday && holiday.name !== 'Fully Booked';

    // Use the bulk fetched fully booked days!
    const isFullyBooked = bulkFullyBooked.includes(dateFormatted);

    let dayClass = [];
    if (isWeekend || isHoliday || isPast) dayClass.push('disabled');
    if (isToday) dayClass.push('today-indicator');
    if (isFullyBooked) dayClass.push('fully-booked');

    const badge = (holiday && holiday.name !== 'Fully Booked') ? '🎉' : '';
    const clickHandler = (!isWeekend && !isHoliday && !isPast && !isFullyBooked)
      ? `onclick="selectDate('${dateFormatted}')"` : '';

    const fullLabel = isFullyBooked ? `<div class="text-danger small">Fully Booked</div>` : '';

    calendarHTML += `
      <div class="day ${dayClass.join(' ')} hover-effect" data-date="${dateFormatted}" ${clickHandler}>
        <div>${day} ${badge}</div>
        ${fullLabel}
      </div>`;
  }

  document.getElementById('calendar').innerHTML = calendarHTML;

  // Debounced slot checking for each day (only if NOT in bulkFullyBooked!)
  // document.querySelectorAll('.day[data-date]').forEach(dayEl => {
  //   const dateStr = dayEl.getAttribute('data-date');
  //   if (bulkFullyBooked.includes(dateStr)) return; // Already marked, skip extra fetch
  //   dayEl.classList.remove('fully-booked', 'disabled');
  //   dayEl.querySelectorAll('.text-danger.small').forEach(el => el.remove());
  //   debouncedFetchTimeSlots(dateStr, dayEl);
  // });

  document.getElementById('selectedDateTitle').textContent = '';
  document.getElementById('timeSlotsSection').style.display = 'none';
  document.getElementById('selectDateReminder').style.display = 'block';
}

// ---- Debounced slot checker ----
function debouncedFetchTimeSlots(dateStr, dayEl) {
  if (!debouncedFetchMap[dateStr]) {
    debouncedFetchMap[dateStr] = debounce((d, el) => {
      fetchTimeSlotsForDay(d, el);
    }, 200);
  }
  debouncedFetchMap[dateStr](dateStr, dayEl);
}
function fetchTimeSlotsForDay(dateStr, dayEl) {
  fetch(`ajax/fetch_time_slots.php?date=${dateStr}`)
    .then(res => res.json())
    .then(data => {
      if (data.isFullyBooked) {
        dayEl.classList.add('fully-booked', 'disabled');
        dayEl.removeAttribute('onclick');
        const label = document.createElement('div');
        label.classList.add('text-danger', 'small');
        label.textContent = 'Fully Booked';
        dayEl.appendChild(label);

        if (!holidays.some(h => h.date === dateStr && h.name === 'Fully Booked')) {
          holidays.push({ date: dateStr, name: 'Fully Booked', isFullyBooked: true });
        }
      } else {
        dayEl.classList.remove('fully-booked', 'disabled');
        dayEl.setAttribute('onclick', `selectDate('${dateStr}')`);
        // Remove "Fully Booked" from holidays if present
        const idx = holidays.findIndex(h => h.date === dateStr && h.name === 'Fully Booked');
        if (idx !== -1) holidays.splice(idx, 1);
      }
    });
}

function formatMonthYear() {
  return currentDate.toLocaleString('default', { month: 'long', year: 'numeric' });
}

function formatLocalDate(date) {
  const yyyy = date.getFullYear();
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  const dd = String(date.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function selectDate(dateStr) {
  const date = new Date(dateStr);
  const isWeekend = date.getDay() === 0 || date.getDay() === 6;
  // Only admin holidays (not fully booked) block selection
  const isHoliday = holidays.some(h => h.date === dateStr && h.name !== 'Fully Booked');

  if (isWeekend || isHoliday) return;

  hasSelectedDate = true;
  selectedDate = date;

  document.getElementById('selectDateReminder').style.display = 'none';
  document.getElementById('timeSlotsSection').style.display = 'block';
  document.getElementById('selectedDateTitle').textContent = `Selected Date: ${dateStr}`;

  loadTimeSlots(dateStr);

  const previouslySelected = document.querySelector('.day.selected');
  if (previouslySelected) previouslySelected.classList.remove('selected');

  const selectedDayElement = document.querySelector(`.day[data-date="${dateStr}"]`);
  if (selectedDayElement) selectedDayElement.classList.add('selected');
}

function loadTimeSlots(dateFormatted) {
  fetch(`ajax/fetch_time_slots.php?date=${dateFormatted}`)
    .then(response => response.json())
    .then(data => {
      if (data.isHoliday) {
        document.getElementById('timeSlotsContainer').innerHTML = `
          <div class="alert alert-warning">
            ⚠️ No appointments available. <strong>${data.holidayName}</strong> is a holiday.
          </div>`;
        return;
      }

      if (data.isFullyBooked && !holidays.some(h => h.date === dateFormatted && h.name === 'Fully Booked')) {
        holidays.push({ date: dateFormatted, name: 'Fully Booked' });
        updateFullyBooked(dateFormatted);
      }

      // 🔎 Normalize (handles slot_limit/limit, dedup, sort, label)
      const slots = normalizeSlots(data);
      // console.table(slots); // <- uncomment to verify you have 2 rows

      let html = '';
      const now = new Date();
      const isToday = formatLocalDate(now) === dateFormatted;

      slots.forEach(slot => {
        // compute start Date for "past slot" check using canonical start_time when available
        const startDate = new Date(dateFormatted);
        if (slot.start_time){
          const S = parseHHMMSS(slot.start_time);
          startDate.setHours(S.h, S.m || 0, 0, 0);
        } else {
          const [startRaw] = slot.label.split(/[-–]/);
          const isPM = /pm$/i.test(startRaw.trim());
          let [hh, mm='0'] = startRaw.replace(/am|pm/i,'').split(':').map(Number);
          if (isPM && hh < 12) hh += 12;
          if (!isPM && hh === 12) hh = 0;
          startDate.setHours(hh, +mm || 0, 0, 0);
        }

        const isFull = Number(slot.booked) >= Number(slot.limit);
        const isPast = isToday && now >= startDate;
        const disabled = (!hasSelectedDate || isFull || isPast) ? 'disabled' : '';
        const btnClass = isFull ? 'btn-danger' : (hasSelectedDate ? 'btn-select' : 'btn-full');

        html += `
          <div class="time-slot d-flex justify-content-between align-items-center">
            <span>${slot.label} (${slot.booked}/${slot.limit} booked)</span>
            <button class="btn ${btnClass}" ${disabled} data-label="${encodeURIComponent(slot.label)}">
              ${isFull ? 'Fully Booked' : 'Select'}
            </button>
          </div>`;
      });

      const container = document.getElementById('timeSlotsContainer');
      container.innerHTML = html;
      container.querySelectorAll('button[data-label]').forEach(btn =>
        btn.addEventListener('click', () =>
          showPurposeModal(decodeURIComponent(btn.getAttribute('data-label')))
        )
      );
    })
    .catch(err => {
      console.error('❌ Failed to load time slots:', err);
      document.getElementById('timeSlotsContainer').innerHTML = '<div class="text-danger">Failed to load time slots.</div>';
    });
}


function updateFullyBooked(dateFormatted) {
  const dayElement = document.querySelector(`.day[data-date="${dateFormatted}"]`);
  if (dayElement) {
    dayElement.classList.add('fully-booked', 'disabled');
    dayElement.removeAttribute('onclick');

    dayElement.querySelectorAll('.text-danger.small').forEach(el => el.remove());
    const fullLabel = document.createElement('div');
    fullLabel.classList.add('text-danger', 'small');
    fullLabel.textContent = 'Fully Booked';
    dayElement.appendChild(fullLabel);
  }
}

function showPurposeModal(time) {
  document.getElementById('purposeModalLabel').textContent = `Enter Purpose for ${time}`;
  new bootstrap.Modal(document.getElementById('purposeModal')).show();
}

function changeMonth(direction) {
  currentDate.setMonth(currentDate.getMonth() + direction);
  populateYearDropdown(currentDate.getFullYear());
  fetchBulkFullyBooked(getMonthStr(currentDate), generateCalendar);
}

function changeYear(year) {
  currentDate.setFullYear(parseInt(year));
  fetchBulkFullyBooked(getMonthStr(currentDate), generateCalendar);
}

function populateYearDropdown(selectedYear) {
  const yearSelect = document.getElementById('yearSelector');
  if (!yearSelect) return;

  yearSelect.innerHTML = '';
  const current = new Date().getFullYear();
  for (let y = current - 5; y <= current + 5; y++) {
    const opt = document.createElement('option');
    opt.value = y;
    opt.textContent = y;
    if (y === selectedYear) opt.selected = true;
    yearSelect.appendChild(opt);
  }
}

//design
// --- Add a subtle loading skeleton while fetching slots ---
const showSlotsLoading = () => {
  const c = document.getElementById('timeSlotsContainer');
  if (!c) return;
  c.innerHTML = `
    <div class="time-slot"><span class="skeleton-line" style="width:60%"></span><span class="skeleton-line" style="width:90px"></span></div>
    <div class="time-slot"><span class="skeleton-line" style="width:40%"></span><span class="skeleton-line" style="width:90px"></span></div>
    <div class="time-slot"><span class="skeleton-line" style="width:70%"></span><span class="skeleton-line" style="width:90px"></span></div>
  `;
};

// Injected into your existing flow: show skeleton right before fetch
const _origLoadTimeSlots = loadTimeSlots;
loadTimeSlots = function(dateFormatted) {
  showSlotsLoading();
  _origLoadTimeSlots.call(this, dateFormatted);
};

// --- Add title tooltips for fully booked cells (Bootstrap if available) ---
function enhanceFullyBookedTooltips(){
  document.querySelectorAll('.day.fully-booked').forEach(el => {
    el.setAttribute('title','No available slots for this date');
    if (window.bootstrap && bootstrap.Tooltip) {
      new bootstrap.Tooltip(el, { placement: 'top', trigger: 'hover' });
    }
  });
}

// Call after you render the calendar each time
const _origGenerateCalendar = generateCalendar;
generateCalendar = function(){
  _origGenerateCalendar.call(this);
  enhanceFullyBookedTooltips();

  // Make cells focusable for keyboard users
  document.querySelectorAll('#calendar .day:not(.disabled)').forEach(el => {
    el.setAttribute('tabindex', '0');
    el.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        const d = el.getAttribute('data-date');
        if (d) selectDate(d);
      }
    });
  });
};

// --- Improve month/year controls appearance (optional) ---
document.querySelectorAll('#prevBtn,#nextBtn').forEach(btn => {
  btn?.classList.add('btn-nav');
});
(function () {
  /* Force a CSS flag on small viewports, even in "Desktop site" mode */
  function setMobileFlag(){
    const isSmall = (window.innerWidth || document.documentElement.clientWidth) <= 768;
    document.documentElement.classList.toggle('is-mobile', isSmall);
  }
  setMobileFlag();
  window.addEventListener('resize', setMobileFlag);

  /* Abbreviate weekday labels to Sun/Mon/Tue… on small screens */
  const map = {
    Sunday:'Sun', Monday:'Mon', Tuesday:'Tue',
    Wednesday:'Wed', Thursday:'Thu', Friday:'Fri', Saturday:'Sat'
  };
  const mql = window.matchMedia('(max-width: 768px)');

  // Find weekday cells inside the calendar by their text
  const weekdayEls = Array.from(document.querySelectorAll('#calendar > *'))
    .filter(el => map[el.textContent.trim()]);

  weekdayEls.forEach(el => {
    const full = el.textContent.trim();
    el.dataset.full = full;
    el.dataset.short = map[full];
  });

  function updateWeekdayText(){
    weekdayEls.forEach(el => {
      el.textContent = (mql.matches ? el.dataset.short : el.dataset.full) || el.textContent;
    });
  }
  updateWeekdayText();
  mql.addEventListener('change', updateWeekdayText);
})();
(function(){
  const MAP = {
    Sunday:'Sun', Monday:'Mon', Tuesday:'Tue', Wednesday:'Wed',
    Thursday:'Thu', Friday:'Fri', Saturday:'Sat'
  };

  // Treat as mobile if any of these indicate a small/coarse device
  const isMobile = () =>
    (window.innerWidth || document.documentElement.clientWidth) <= 768 ||
    window.matchMedia('(hover:none) and (pointer:coarse)').matches ||
    Math.min(screen.width, screen.height) <= 820;

  function applyAbbrev(){
    const cal = document.getElementById('calendar');
    if (!cal) return;

    const mobile = isMobile();

    // 1) Mark any new weekday elements (any depth)
    cal.querySelectorAll('*').forEach(el=>{
      if (el.dataset && el.dataset.dowProcessed) return;
      const txt = (el.textContent || '').trim();
      if (!MAP[txt]) return;                 // not a weekday
      el.dataset.full = txt;
      el.dataset.short = MAP[txt] || txt.slice(0,3);
      el.dataset.dowProcessed = '1';
    });

    // 2) Flip all marked labels based on current mode
    cal.querySelectorAll('[data-dow-processed]').forEach(el=>{
      const target = mobile ? el.dataset.short : el.dataset.full;
      if (el.textContent !== target) el.textContent = target;
    });
  }

  // Throttle with rAF to avoid loops
  let raf = null;
  const schedule = () => {
    if (raf) cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => { raf = null; applyAbbrev(); });
  };

  // initial + load (some phones finalize layout only on 'load')
  document.readyState !== 'loading' ? schedule() : document.addEventListener('DOMContentLoaded', schedule);
  window.addEventListener('load', schedule);
  window.addEventListener('resize', schedule);
  window.addEventListener('orientationchange', schedule);

  // Re-run whenever the calendar re-renders (Prev/Next)
  const startObserver = () => {
    const cal = document.getElementById('calendar');
    if (!cal) return;
    const mo = new MutationObserver(schedule);
    mo.observe(cal, { childList: true, subtree: true }); // watch any depth
  };
  startObserver();
})();