(function () {
	'use strict';

	var PARTICIPANT_KEYS = ['captain', 'member_2', 'member_3', 'member_4'];

	function setupForm(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var registrationTypeField = form.querySelector('[name="registration_type"]');
		var currentStep = 0;

		function getRegistrationType() {
			return registrationTypeField ? registrationTypeField.value : 'individual';
		}

		function getVisibleParticipantKeys() {
			var registrationType = getRegistrationType();

			if (registrationType === 'team') {
				return PARTICIPANT_KEYS;
			}

			if (registrationType === 'individual') {
				return ['captain'];
			}

			return [];
		}

		function isStepVisible(step) {
			var participantKey = step.getAttribute('data-participant-step');

			if (!participantKey) {
				return true;
			}

			return getVisibleParticipantKeys().indexOf(participantKey) !== -1;
		}

		function getVisibleSteps() {
			return steps.filter(isStepVisible);
		}

		function getStepLabel(step) {
			var stepKey = step.getAttribute('data-step-key');

			if (!stepKey) {
				return null;
			}

			return form.querySelector('[data-step-label-key="' + stepKey + '"]');
		}

		function getNumberValue(name) {
			var field = form.querySelector('[name="' + name + '"]');
			var value = field ? parseInt(field.value, 10) : 0;

			return isNaN(value) || value < 0 ? 0 : value;
		}

		function getPrice(key) {
			var value = parseFloat(form.getAttribute('data-' + key + '-price'));

			return isNaN(value) || value < 0 ? 0 : value;
		}

		function formatCurrency(amount) {
			return new Intl.NumberFormat('en-US', {
				currency: 'USD',
				style: 'currency'
			}).format(amount);
		}

		function setReviewValue(selector, value) {
			var node = form.querySelector(selector);

			if (node) {
				node.textContent = value;
			}
		}

		function getSelectedOptionText(field) {
			if (!field || field.selectedIndex < 0) {
				return '';
			}

			return field.options[field.selectedIndex].text;
		}

		function calculateSummary() {
			var visibleParticipantKeys = getVisibleParticipantKeys();
			var golfQty = visibleParticipantKeys.reduce(function (total, participantKey) {
				var field = form.querySelector('[name="' + participantKey + '_participation_type"]');

				return total + (field && field.value === 'golf' ? 1 : 0);
			}, 0);
			var lunchQty = getNumberValue('additional_lunch_count');
			var dinnerQty = getNumberValue('additional_dinner_count');
			var sponsorshipField = form.querySelector('[name="sponsorship_level"]');
			var sponsorshipLevel = sponsorshipField ? sponsorshipField.value : '';
			var sponsorPrice = sponsorshipLevel ? getPrice(sponsorshipLevel + '-sponsor') : 0;
			var subtotal = (golfQty * getPrice('golf')) + (lunchQty * getPrice('lunch')) + (dinnerQty * getPrice('dinner')) + sponsorPrice;
			var discount = 0;
			var grandTotal = Math.max(0, subtotal - discount);

			setReviewValue('[data-review-event-title]', form.getAttribute('data-event-title') || '');
			setReviewValue('[data-review-registration-type]', getSelectedOptionText(registrationTypeField));
			setReviewValue('[data-review-golf-qty]', String(golfQty));
			setReviewValue('[data-review-lunch-qty]', String(lunchQty));
			setReviewValue('[data-review-dinner-qty]', String(dinnerQty));
			setReviewValue('[data-review-sponsor-level]', getSelectedOptionText(sponsorshipField) || 'None');
			setReviewValue('[data-review-subtotal]', formatCurrency(subtotal));
			setReviewValue('[data-review-discount]', formatCurrency(discount));
			setReviewValue('[data-review-grand-total]', formatCurrency(grandTotal));
		}

		function updateCaptainLabels() {
			var isIndividual = getRegistrationType() === 'individual';
			var title = form.querySelector('[data-captain-step-title]');
			var label = form.querySelector('[data-captain-step-label]');
			var legend = form.querySelector('[data-participant-step="captain"] legend');
			var participantLabel = label ? (isIndividual ? label.getAttribute('data-individual-label') : label.getAttribute('data-team-label')) : '';

			if (title) {
				title.textContent = isIndividual ? title.getAttribute('data-individual-label') : title.getAttribute('data-team-label');
			}

			if (label) {
				label.textContent = participantLabel;
			}

			if (legend && participantLabel) {
				legend.textContent = participantLabel;
			}
		}

		function updateVisibility() {
			steps.forEach(function (step) {
				var visible = isStepVisible(step);
				var label = getStepLabel(step);

				step.setAttribute('data-step-available', visible ? 'true' : 'false');

				if (!visible) {
					step.hidden = true;
				}

				if (label) {
					label.hidden = !visible;
				}
			});

			updateCaptainLabels();
		}

		function showStep(index) {
			var visibleSteps;
			var currentVisibleStep;

			updateVisibility();
			visibleSteps = getVisibleSteps();
			currentStep = Math.max(0, Math.min(index, visibleSteps.length - 1));
			currentVisibleStep = visibleSteps[currentStep];

			steps.forEach(function (step) {
				step.hidden = step !== currentVisibleStep;
			});

			stepLabels.forEach(function (label) {
				var matchingStep = steps.filter(function (step) {
					return getStepLabel(step) === label;
				})[0];
				var visibleIndex = visibleSteps.indexOf(matchingStep);

				label.classList.toggle('is-active', visibleIndex === currentStep);
				label.classList.toggle('is-complete', visibleIndex > -1 && visibleIndex < currentStep);
			});

			if (backButton) {
				backButton.hidden = currentStep === 0;
			}

			if (nextButton) {
				nextButton.hidden = currentStep === visibleSteps.length - 1;
			}

			calculateSummary();
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				showStep(currentStep - 1);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				showStep(currentStep + 1);
			});
		}

		if (registrationTypeField) {
			registrationTypeField.addEventListener('change', function () {
				showStep(currentStep);
			});
		}

		form.addEventListener('input', calculateSummary);
		form.addEventListener('change', calculateSummary);

		showStep(0);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), setupForm);
	});
}());
