document.addEventListener('click', function (event) {
    const day = event.target.closest('[data-gkpsch-month-day]');
    if (day) {
        const schedule = day.closest('.gkpsch-schedule');
        const panel = document.getElementById(day.hash.slice(1));
        if (schedule && panel && schedule.contains(panel)) {
            event.preventDefault();
            schedule.querySelectorAll('.gkpsch-month-day-panel').forEach(item => { item.open = item === panel; });
            schedule.querySelectorAll('[data-gkpsch-month-day]').forEach(item => { if (item === day) item.setAttribute('aria-current', 'date'); else item.removeAttribute('aria-current'); });
            schedule.querySelector('[name="gkpsch_date"]').value = day.dataset.gkpschMonthDay;
            const url = new URL(window.location.href);
            url.searchParams.set('gkpsch_date', day.dataset.gkpschMonthDay);
            window.history.replaceState(null, '', url);
            panel.querySelector('summary').focus({preventScroll:true}); panel.scrollIntoView({block:'nearest'});
        }
    }
    if (event.target.closest('.gkpsch-print')) {
        document.body.classList.add('gkpsch-printing');
        window.print();
    }
});
window.addEventListener('afterprint', function () { document.body.classList.remove('gkpsch-printing'); });
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.gkpsch-month-panels').forEach(panels => panels.classList.add('is-enhanced'));
});
