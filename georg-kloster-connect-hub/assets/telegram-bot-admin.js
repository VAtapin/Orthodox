(function () {
	'use strict';

	const rows = document.getElementById('gkch-telegram-command-rows');
	const addButton = document.getElementById('gkch-add-telegram-command');
	const template = document.getElementById('gkch-telegram-command-template');

	if (!rows || !addButton || !template) {
		return;
	}

	let nextIndex = rows.querySelectorAll('.gkch-telegram-command-row').length;
	const syncRow = function (row) {
		const type = row.querySelector('.gkch-responder-type');
		const isText = type && type.value === 'text';
		row.querySelectorAll('.gkch-command-field').forEach(function (cell) { cell.hidden = isText; });
		row.querySelectorAll('.gkch-trigger-field').forEach(function (cell) { cell.hidden = !isText; });
	};

	rows.querySelectorAll('.gkch-telegram-command-row').forEach(syncRow);

	addButton.addEventListener('click', function () {
		const html = template.innerHTML.replaceAll('__index__', String(nextIndex++));
		rows.insertAdjacentHTML('beforeend', html);
		const row = rows.lastElementChild;
		syncRow(row);
		const command = row.querySelector('input[name$="[command]"]');
		if (command) {
			command.focus();
		}
	});

	rows.addEventListener('change', function (event) {
		if (event.target.matches('.gkch-responder-type')) {
			syncRow(event.target.closest('.gkch-telegram-command-row'));
		}
	});

	rows.addEventListener('click', function (event) {
		const button = event.target.closest('.gkch-remove-telegram-command');
		if (!button) {
			return;
		}

		const row = button.closest('.gkch-telegram-command-row');
		if (row) {
			row.remove();
		}
	});
})();
