(function () {
	'use strict';

	const replyType = document.getElementById('gkch-reply-type');
	const scheduleOption = document.querySelector('.gkch-schedule-option');
	const postOption = document.querySelector('.gkch-post-option');
	const postSelect = document.getElementById('gkch-reply-post');

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
})();
