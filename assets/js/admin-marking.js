(function ($) {
	'use strict';

	var modal = $('#robokassa-marking-modal');
	var fields = modal.find('.robokassa-marking-fields');
	var message = modal.find('.robokassa-marking-message');
	var currentButton = null;

	function bytesToBase64(value) {
		var bytes = new TextEncoder().encode(value);
		var binary = '';

		bytes.forEach(function (byte) {
			binary += String.fromCharCode(byte);
		});

		return window.btoa(binary);
	}

	function base64ToText(value) {
		if (!value) {
			return '';
		}

		var binary = window.atob(value);
		var bytes = Uint8Array.from(binary, function (character) {
			return character.charCodeAt(0);
		});

		return new TextDecoder().decode(bytes);
	}

	function showMessage(text, isError) {
		message.text(text).toggleClass('is-error', !!isError).prop('hidden', false);
	}

	function closeModal() {
		modal.prop('hidden', true);
		fields.empty();
		message.prop('hidden', true).text('');
		currentButton = null;
	}

	function renderFields(data) {
		fields.empty();

		for (var index = 0; index < data.quantity; index++) {
			var row = $('<label class="robokassa-marking-field"></label>');
			var label = $('<span></span>').text('Товар #' + (index + 1));
			var input = $('<input type="text" class="widefat" autocomplete="off">');
			input.val(base64ToText(data.codes[index] || ''));
			row.append(label, input);
			fields.append(row);
		}

		modal.find('#robokassa-marking-title').text('Маркировка: ' + data.name);
		modal.prop('hidden', false);
		fields.find('input').filter(function () { return !this.value; }).first().trigger('focus');
	}

	$(document).on('click', '.robokassa-marking-open', function () {
		currentButton = $(this);
		message.prop('hidden', true);

		$.post(robokassaMarking.ajaxUrl, {
			action: 'robokassa_get_marking_codes',
			nonce: robokassaMarking.nonce,
			itemId: currentButton.data('item-id')
		}).done(function (response) {
			if (!response.success) {
				showMessage(response.data.message || 'Не удалось загрузить маркировку.', true);
				return;
			}

			renderFields(response.data);
		}).fail(function () {
			window.alert('Не удалось загрузить коды маркировки. Обновите страницу и попробуйте снова.');
		});
	});

	modal.on('click', '.robokassa-marking-close, .robokassa-marking-cancel', closeModal);
	modal.on('click', function (event) {
		if (event.target === modal[0]) {
			closeModal();
		}
	});

	modal.on('keydown', 'input', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
			$(this).closest('.robokassa-marking-field').next().find('input').trigger('focus');
		}
	});

	modal.on('click', '.robokassa-marking-save', function () {
		var button = $(this);
		var codes = fields.find('input').map(function () {
			return this.value ? bytesToBase64(this.value) : '';
		}).get();

		button.prop('disabled', true);
		message.prop('hidden', true);

		$.post(robokassaMarking.ajaxUrl, {
			action: 'robokassa_save_marking_codes',
			nonce: robokassaMarking.nonce,
			itemId: currentButton.data('item-id'),
			codes: codes
		}).done(function (response) {
			if (!response.success) {
				showMessage(response.data.message || 'Не удалось сохранить маркировку.', true);
				return;
			}

			currentButton.text(response.data.filled + ' из ' + response.data.quantity);
			currentButton.toggleClass('is-complete', response.data.filled === response.data.quantity);
			closeModal();
		}).fail(function (xhr) {
			var response = xhr.responseJSON;
			showMessage(response && response.data ? response.data.message : 'Не удалось сохранить маркировку.', true);
		}).always(function () {
			button.prop('disabled', false);
		});
	});
})(jQuery);
