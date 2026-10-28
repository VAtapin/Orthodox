(function () {
	'use strict';

	const replyType = document.getElementById('gkch-reply-type');
	const scheduleOption = document.querySelector('.gkch-schedule-option');
	const postOption = document.querySelector('.gkch-post-option');
	const postSelect = document.getElementById('gkch-reply-post');
	const responseOptions = document.querySelectorAll('.gkch-response-option');
	const mediaOptions = document.querySelectorAll('.gkch-media-option');
	const mediaLabels = window.gkchTelegramMedia || {};

	if (!replyType) {
		return;
	}

	const syncReplyOptions = function () {
		const type = replyType.value;
		const postType = 'page' === type ? 'page' : ('news' === type ? 'post' : '');

		if (scheduleOption) {
			scheduleOption.hidden = 'schedule' !== type;
		}
		if (postOption) {
			postOption.hidden = '' === postType;
		}
		responseOptions.forEach(function (option) {
			option.hidden = 'page' === type || 'news' === type;
		});
		mediaOptions.forEach(function (option) {
			option.hidden = 'page' === type || 'news' === type;
		});
		if (postSelect) {
			Array.from(postSelect.options).forEach(function (option) {
				if (!option.dataset.postType) {
					return;
				}
				const matches = option.dataset.postType === postType;
				option.hidden = !matches;
				option.disabled = !matches;
			});
			const selected = postSelect.options[postSelect.selectedIndex];
			if (selected && selected.dataset.postType && selected.dataset.postType !== postType) {
				postSelect.value = '0';
			}
		}
	};

	replyType.addEventListener('change', syncReplyOptions);
	syncReplyOptions();

	document.querySelectorAll('.gkch-media-option').forEach(function (control) {
		const input = control.querySelector('.gkch-telegram-media-ids');
		const button = control.querySelector('.gkch-telegram-media-select');
		const preview = control.querySelector('.gkch-telegram-media-preview');
		let frame;

		const allowed = function (attachment) {
			return ['image/jpeg', 'image/png', 'video/mp4'].includes(attachment.mime);
		};
		const label = function (attachment) {
			const kind = attachment.mime === 'video/mp4' ? mediaLabels.video : mediaLabels.photo;
			return (kind || '') + ': ' + (attachment.title || attachment.filename || ('#' + attachment.id));
		};
		const render = function (items) {
			preview.replaceChildren();
			items.forEach(function (attachment) {
				const row = document.createElement('li');
				row.dataset.attachmentId = String(attachment.id);
				const name = document.createElement('span');
				name.textContent = label(attachment);
				const remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'button-link-delete gkch-telegram-media-remove';
				remove.textContent = mediaLabels.remove || 'Remove';
				row.append(name, document.createTextNode(' '), remove);
				preview.append(row);
			});
		};

		button.addEventListener('click', function () {
			if (!window.wp || !wp.media) {
				return;
			}
			if (!frame) {
				frame = wp.media({
					title: mediaLabels.title || '',
					button: { text: mediaLabels.button || '' },
					multiple: true
				});
				frame.on('open', function () {
					const selection = frame.state().get('selection');
					selection.reset();
					(input.value || '').split(',').filter(Boolean).forEach(function (id) {
						const attachment = wp.media.attachment(id);
						selection.add(attachment);
						attachment.fetch();
					});
				});
				frame.on('select', function () {
					const selected = frame.state().get('selection').toJSON();
					const items = selected.filter(allowed);
					if (items.length !== selected.length) {
						window.alert(mediaLabels.invalid || '');
					}
					if (items.length > 10) {
						window.alert(mediaLabels.limit || '');
					}
					const limited = items.slice(0, 10);
					input.value = limited.map(function (attachment) { return attachment.id; }).join(',');
					render(limited);
				});
			}
			frame.open();
		});

		preview.addEventListener('click', function (event) {
			const remove = event.target.closest('.gkch-telegram-media-remove');
			if (!remove) {
				return;
			}
			const row = remove.closest('[data-attachment-id]');
			const id = row ? row.dataset.attachmentId : '';
			const selection = frame && frame.state().get('selection');
			if (selection && id) {
				const attachment = selection.get(id);
				if (attachment) {
					selection.remove(attachment);
				}
			}
			if (row) {
				row.remove();
			}
			input.value = Array.from(preview.querySelectorAll('[data-attachment-id]'))
				.map(function (item) { return item.dataset.attachmentId; })
				.join(',');
		});
	});

	const menuButtons = document.querySelector('.gkch-menu-buttons');
	const addMenuButton = document.querySelector('.gkch-menu-button-add');
	const menuButtonTemplate = document.getElementById('gkch-menu-button-template');
	if (menuButtons && addMenuButton && menuButtonTemplate) {
		const syncMenuButton = function (row) {
			const action = row.querySelector('.gkch-menu-action');
			const targetField = row.querySelector('.gkch-menu-target-field');
			const target = row.querySelector('.gkch-menu-target');
			const urlField = row.querySelector('.gkch-menu-url-field');
			const url = row.querySelector('.gkch-menu-url');
			const needsUrl = action && ['link', 'web_app'].includes(action.value);
			if (targetField) { targetField.hidden = needsUrl; }
			if (target) { target.disabled = needsUrl; }
			if (urlField) { urlField.hidden = !needsUrl; }
			if (url) { url.disabled = !needsUrl; }
		};
		const syncMenuButtonCount = function () {
			addMenuButton.disabled = menuButtons.querySelectorAll('[data-menu-button]').length >= 10;
		};
		menuButtons.querySelectorAll('[data-menu-button]').forEach(syncMenuButton);
		menuButtons.addEventListener('change', function (event) {
			if (event.target.matches('.gkch-menu-action')) {
				syncMenuButton(event.target.closest('[data-menu-button]'));
			}
		});
		menuButtons.addEventListener('click', function (event) {
			const remove = event.target.closest('.gkch-menu-button-remove');
			if (!remove) {
				return;
			}
			const row = remove.closest('[data-menu-button]');
			if (row) {
				row.remove();
				syncMenuButtonCount();
			}
		});
		addMenuButton.addEventListener('click', function () {
			if (menuButtons.querySelectorAll('[data-menu-button]').length >= 10) {
				window.alert(mediaLabels.menuLimit || '');
				return;
			}
			const index = parseInt(menuButtons.dataset.nextIndex || '0', 10);
			menuButtons.dataset.nextIndex = String(index + 1);
			const html = menuButtonTemplate.innerHTML.replace(/__INDEX__/g, String(index));
			const holder = document.createElement('div');
			holder.innerHTML = html.trim();
			const row = holder.firstElementChild;
			if (row) {
				menuButtons.append(row);
				syncMenuButton(row);
				syncMenuButtonCount();
			}
		});
		syncMenuButtonCount();
	}
})();
