document.addEventListener('click', function (event) {
    if (event.target.closest('.prihod-print')) {
        document.body.classList.add('prihod-printing');
        window.print();
    }
});
window.addEventListener('afterprint', function () { document.body.classList.remove('prihod-printing'); });
