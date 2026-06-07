(function () {
	'use strict';

	function restoreEscapedTooltipMarkup(labelCell) {
		var text = labelCell.textContent || '';
		var marker = '<span class="tfwga-tooltip"';
		var markerIndex = text.indexOf(marker);

		if (markerIndex === -1) {
			return;
		}

		var labelText = text.slice(0, markerIndex).trim();
		var tooltipMarkup = text.slice(markerIndex).trim();
		var template = document.createElement('template');

		template.innerHTML = tooltipMarkup;

		labelCell.textContent = labelText ? labelText + ' ' : '';
		labelCell.appendChild(template.content.cloneNode(true));
	}

	document.addEventListener('DOMContentLoaded', function () {
		document
			.querySelectorAll('.shop_attributes th, .woocommerce-product-attributes th, .woocommerce-product-attributes-item__label')
			.forEach(restoreEscapedTooltipMarkup);
	});
})();
