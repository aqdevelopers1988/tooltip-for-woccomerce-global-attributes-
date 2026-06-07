(function () {
	'use strict';

	var config = window.tfwgaFrontend || {};
	var tooltipData = Array.isArray(config.tooltips) ? config.tooltips : [];
	var activeModal = null;
	var lastTrigger = null;

	function normalizeLabel(value) {
		return String(value || '')
			.replace(/<[^>]*>/g, '')
			.replace(/\s+/g, ' ')
			.trim()
			.toLowerCase();
	}

	function findTooltipForLabel(label) {
		var normalized = normalizeLabel(label);

		return tooltipData.find(function (item) {
			return normalizeLabel(item.label) === normalized;
		});
	}

	function buildTooltipButton(item) {
		var button = document.createElement('span');
		button.setAttribute('role', 'button');
		button.setAttribute('tabindex', '0');
		button.className = 'tfwga-tooltip';
		button.setAttribute('aria-label', 'View ' + item.label + ' tooltip');
		button.dataset.tfwgaTitle = item.label || '';
		button.dataset.tfwgaContent = item.tooltip || '';

		if (item.iconUrl) {
			var image = document.createElement('img');
			image.className = 'tfwga-tooltip__image';
			image.src = item.iconUrl;
			image.alt = '';
			button.appendChild(image);
		} else {
			var fallbackIcon = document.createElement('span');
			fallbackIcon.className = 'tfwga-tooltip__fallback-icon';
			fallbackIcon.setAttribute('aria-hidden', 'true');
			fallbackIcon.textContent = '!';
			button.appendChild(fallbackIcon);
		}

		return button;
	}

	function addTooltipToLabelCell(labelCell) {
		if (labelCell.querySelector('.tfwga-tooltip')) {
			return;
		}

		var item = findTooltipForLabel(labelCell.textContent);

		if (!item) {
			return;
		}

		labelCell.insertBefore(document.createTextNode(' '), labelCell.firstChild);
		labelCell.insertBefore(buildTooltipButton(item), labelCell.firstChild);
		labelCell.classList.add('tfwga-tooltip-label');
	}

	function restoreEscapedTooltipMarkup(labelCell) {
		var text = labelCell.textContent || '';
		var spanMarker = '<span class="tfwga-tooltip"';
		var buttonMarker = '<button type="button" class="tfwga-tooltip"';
		var spanButtonMarker = '<span class="tfwga-tooltip"';
		var marker = text.indexOf(buttonMarker) !== -1 ? buttonMarker : spanMarker;

		if (text.indexOf(spanButtonMarker) !== -1) {
			marker = spanButtonMarker;
		}
		var markerIndex = text.indexOf(marker);

		if (markerIndex === -1) {
			return;
		}

		var labelText = text.slice(0, markerIndex).trim();
		labelCell.textContent = labelText;
		labelCell.classList.remove('tfwga-tooltip-label');
	}

	function closeModal() {
		if (!activeModal) {
			return;
		}

		activeModal.remove();
		activeModal = null;
		document.documentElement.classList.remove('tfwga-modal-open');

		if (lastTrigger) {
			lastTrigger.focus();
		}
	}

	function openModal(trigger) {
		var title = trigger.dataset.tfwgaTitle || trigger.getAttribute('aria-label') || '';
		var content = trigger.dataset.tfwgaContent || '';
		var modal = document.createElement('div');

		closeModal();
		lastTrigger = trigger;
		modal.className = 'tfwga-tooltip-modal';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.innerHTML =
			'<div class="tfwga-tooltip-modal__overlay" data-tfwga-close></div>' +
			'<div class="tfwga-tooltip-modal__dialog" role="document">' +
			'<span class="tfwga-tooltip-modal__close" data-tfwga-close role="button" tabindex="0" aria-label="' +
			(config.closeLabel || 'Close tooltip') +
			'">×</span>' +
			'<h3 class="tfwga-tooltip-modal__title"></h3>' +
			'<div class="tfwga-tooltip-modal__content"></div>' +
			'</div>';

		modal.querySelector('.tfwga-tooltip-modal__title').textContent = title;
		modal.querySelector('.tfwga-tooltip-modal__content').innerHTML = content;
		document.body.appendChild(modal);
		document.documentElement.classList.add('tfwga-modal-open');
		activeModal = modal;
		modal.querySelector('.tfwga-tooltip-modal__close').focus();
	}


	function isKeyboardActivation(event) {
		return event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar';
	}

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('.tfwga-tooltip');

		if (trigger) {
			event.preventDefault();
			openModal(trigger);
			return;
		}

		if (event.target.closest('[data-tfwga-close]')) {
			event.preventDefault();
			closeModal();
		}
	});

	document.addEventListener('keydown', function (event) {
		var trigger = event.target.closest('.tfwga-tooltip');
		var closeTrigger = event.target.closest('[data-tfwga-close]');

		if (trigger && isKeyboardActivation(event)) {
			event.preventDefault();
			openModal(trigger);
			return;
		}

		if (closeTrigger && isKeyboardActivation(event)) {
			event.preventDefault();
			closeModal();
			return;
		}

		if (event.key === 'Escape') {
			closeModal();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		document
			.querySelectorAll('.shop_attributes th, .woocommerce-product-attributes th, .woocommerce-product-attributes-item__label, .tdv-specifications-table .tdv-spec-label')
			.forEach(function (labelCell) {
				restoreEscapedTooltipMarkup(labelCell);
				addTooltipToLabelCell(labelCell);
			});
	});
})();
