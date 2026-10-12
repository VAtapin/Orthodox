document.addEventListener('click', function (event) {
    const day = event.target.closest('[data-prihod-month-day]');
    if (day) {
        const schedule = day.closest('.prihod-schedule');
        const panel = document.getElementById(day.hash.slice(1));
        if (schedule && panel && schedule.contains(panel)) {
            event.preventDefault();
            schedule.querySelectorAll('.prihod-month-day-panel').forEach(item => { item.open = item === panel; });
            schedule.querySelectorAll('[data-prihod-month-day]').forEach(item => { if (item === day) item.setAttribute('aria-current', 'date'); else item.removeAttribute('aria-current'); });
            schedule.querySelector('[name="prihod_date"]').value = day.dataset.prihodMonthDay;
            const url = new URL(window.location.href);
            url.searchParams.set('prihod_date', day.dataset.prihodMonthDay);
            window.history.replaceState(null, '', url);
            panel.querySelector('summary').focus({preventScroll:true}); panel.scrollIntoView({block:'nearest'});
        }
    }
    if (event.target.closest('.prihod-print')) {
        document.body.classList.add('prihod-printing');
        window.print();
    }
});
window.addEventListener('afterprint', function () { document.body.classList.remove('prihod-printing'); });
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.prihod-month-panels').forEach(panels => panels.classList.add('is-enhanced'));
});
