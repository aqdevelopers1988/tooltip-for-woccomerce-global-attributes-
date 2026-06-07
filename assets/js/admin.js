(function ($) {
	'use strict';

	$(function () {
		$('.tfwga-color-field').wpColorPicker();

		var mediaFrame;

		$('.tfwga-upload-icon').on('click', function (event) {
			event.preventDefault();

			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			mediaFrame = wp.media({
				title: tfwgaAdmin.chooseIcon,
				button: {
					text: tfwgaAdmin.useIcon
				},
				multiple: false
			});

			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				var url = attachment.url || '';

				$('.tfwga-icon-url').val(url);
				$('.tfwga-icon-preview').html(url ? '<img src="' + url + '" alt="" />' : '');
			});

			mediaFrame.open();
		});

		$('.tfwga-remove-icon').on('click', function (event) {
			event.preventDefault();
			$('.tfwga-icon-url').val('');
			$('.tfwga-icon-preview').empty();
		});
	});
})(jQuery);
