(function () {
	'use strict';

	const rows = document.getElementById('gkch-telegram-command-rows');
	const addButton = document.getElementById('gkch-add-telegram-command');
	const template = document.getElementById('gkch-telegram-command-template');

	if (!rows || !addButton || !template) {
		return;
	}

	let nextIndex = rows.querySelectorAll('.gkch-telegram-command-row').length;

	addButton.addEventListener('click', function () {
		const html = template.innerHTML.replaceAll('__index__', String(nextIndex++));
		rows.insertAdjacentHTML('beforeend', html);
		const command = rows.lastElementChild.querySelector('input[name$="[command]"]');
		if (command) {
			command.focus();
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
