(function () {
	'use strict';

	var PARTICIPANT_KEYS = ['captain', 'member_2', 'member_3', 'member_4'];
	var SPONSOR_LABELS = {
		'': 'None',
		platinum: 'Platinum Sponsor',
		gold: 'Gold Sponsor',
		silver: 'Silver Sponsor'
	};
	var REGISTRATION_LABELS = {
		team: 'Team',
		individual: 'Individual',
		sponsor_only: 'Sponsor Only'
	};

	function getField(form, name) {
		return form.querySelector('[name="' + name + '"]');
	}

	function getFieldValue(form, name) {
		var field = getField(form, name);
		return field ? field.value : '';
	}

	function getNumericFieldValue(form, name) {
		var value = parseInt(getFieldValue(form, name), 10);
		return Number.isNaN(value) ? 0 : Math.max(0, value);
	}

	function isChecked(form, name) {
		var field = getField(form, name);
		return !!(field && field.checked);
	}

	function getPrice(form, key) {
		var value = parseFloat(form.dataset[key] || '0');
		return Number.isNaN(value) ? 0 : Math.max(0, value);
	}

	function money(amount) {
		return '$' + amount.toFixed(2);
	}

	function setSummary(form, key, value) {
		var target = form.querySelector('[data-summary="' + key + '"]');
		if (target) {
			target.textContent = value;
		}
	}

	function getVisibleStepKeys(registrationType) {
		var keys = ['registration_type', 'main_contact'];

		if (registrationType === 'team') {
			keys = keys.concat(PARTICIPANT_KEYS, ['additional_guests']);
		} else if (registrationType === 'individual') {
			keys = keys.concat(['captain', 'additional_guests']);
		}

		keys.push('sponsorship', 'review');
		return keys;
	}

	function updateDynamicLabels(form, registrationType) {
		var labelType = registrationType === 'individual' ? 'individualLabel' : 'teamLabel';

		Array.prototype.forEach.call(form.querySelectorAll('[data-team-label][data-individual-label]'), function (node) {
			node.textContent = node.dataset[labelType] || node.textContent;
		});
	}

	function updateReview(form) {
		var registrationType = getFieldValue(form, 'registration_type') || 'individual';
		var golfQty = 0;
		var lunchQty = 0;
		var dinnerQty = 0;
		var participantKeys = [];

		if (registrationType === 'team') {
			participantKeys = PARTICIPANT_KEYS;
		} else if (registrationType === 'individual') {
			participantKeys = ['captain'];
		}

		participantKeys.forEach(function (participantKey) {
			var participationType = getFieldValue(form, participantKey + '_participation_type');

			if (participationType === 'golf') {
				golfQty += 1;
			} else if (participationType === 'lunch') {
				lunchQty += 1;
			} else if (participationType === 'dinner') {
				dinnerQty += 1;
			}
		});

		if (registrationType !== 'sponsor_only') {
			lunchQty += getNumericFieldValue(form, 'additional_lunch_count');
			dinnerQty += getNumericFieldValue(form, 'additional_dinner_count');
		}

		var sponsorLevel = getFieldValue(form, 'sponsorship_level');
		var teeSponsorSelected = isChecked(form, 'tee_sponsor_selected');
		var subtotal = (golfQty * getPrice(form, 'golfPrice')) +
			(lunchQty * getPrice(form, 'lunchPrice')) +
			(dinnerQty * getPrice(form, 'dinnerPrice'));

		if (sponsorLevel) {
			subtotal += getPrice(form, sponsorLevel + 'SponsorPrice');
		}

		if (teeSponsorSelected) {
			subtotal += getPrice(form, 'teeSponsorPrice');
		}

		setSummary(form, 'registration_type', REGISTRATION_LABELS[registrationType] || registrationType);
		setSummary(form, 'golf_qty', String(golfQty));
		setSummary(form, 'lunch_qty', String(lunchQty));
		setSummary(form, 'dinner_qty', String(dinnerQty));
		setSummary(form, 'sponsorship_level', SPONSOR_LABELS[sponsorLevel] || sponsorLevel || SPONSOR_LABELS['']);
		setSummary(form, 'tee_sponsor_selected', teeSponsorSelected ? 'Yes' : 'No');
		setSummary(form, 'subtotal', money(subtotal));
		setSummary(form, 'discount_amount', money(0));
		setSummary(form, 'grand_total', money(subtotal));
	}

	function setupForm(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var currentStepKey = 'registration_type';

		function getRegistrationType() {
			return getFieldValue(form, 'registration_type') || 'individual';
		}

		function getVisibleSteps() {
			var visibleKeys = getVisibleStepKeys(getRegistrationType());
			return steps.filter(function (step) {
				return visibleKeys.indexOf(step.dataset.stepKey) !== -1;
			});
		}

		function showStepByKey(stepKey) {
			var registrationType = getRegistrationType();
			var visibleKeys = getVisibleStepKeys(registrationType);
			var visibleSteps = getVisibleSteps();
			var visibleIndex = visibleKeys.indexOf(stepKey);

			if (visibleIndex === -1) {
				visibleIndex = 0;
			}

			currentStepKey = visibleKeys[visibleIndex];
			updateDynamicLabels(form, registrationType);

			steps.forEach(function (step) {
				step.hidden = step.dataset.stepKey !== currentStepKey;
			});

			stepLabels.forEach(function (label) {
				var labelIndex = visibleKeys.indexOf(label.dataset.stepKey);
				var isVisible = labelIndex !== -1;

				label.hidden = !isVisible;
				label.classList.toggle('is-active', label.dataset.stepKey === currentStepKey);
				label.classList.toggle('is-complete', isVisible && labelIndex < visibleIndex);
			});

			if (backButton) {
				backButton.hidden = visibleIndex === 0;
			}

			if (nextButton) {
				nextButton.hidden = visibleIndex === visibleSteps.length - 1;
			}

			updateReview(form);
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				var visibleKeys = getVisibleStepKeys(getRegistrationType());
				showStepByKey(visibleKeys[Math.max(0, visibleKeys.indexOf(currentStepKey) - 1)]);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				var visibleKeys = getVisibleStepKeys(getRegistrationType());
				showStepByKey(visibleKeys[Math.min(visibleKeys.length - 1, visibleKeys.indexOf(currentStepKey) + 1)]);
			});
		}

		form.addEventListener('input', function () {
			updateReview(form);
		});

		form.addEventListener('change', function (event) {
			if (event.target && event.target.name === 'registration_type') {
				showStepByKey(currentStepKey);
				return;
			}

			updateReview(form);
		});

		showStepByKey(currentStepKey);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), setupForm);
	});
}());
