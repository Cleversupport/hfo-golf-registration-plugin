(function () {
	'use strict';

	var participantSteps = ['captain', 'member_2', 'member_3', 'member_4'];
	var sponsorLabels = {
		platinum: 'Platinum Sponsor',
		gold: 'Gold Sponsor',
		silver: 'Silver Sponsor',
		tee: 'Tee Sponsor'
	};
	var registrationTypeLabels = {
		team: 'Team',
		individual: 'Individual',
		sponsor_only: 'Sponsor Only'
	};

	function getFieldValue(form, name) {
		var field = form.querySelector('[name="' + name + '"]');
		return field ? field.value : '';
	}

	function getNumberFieldValue(form, name) {
		var value = parseInt(getFieldValue(form, name), 10);
		return isNaN(value) ? 0 : Math.max(0, value);
	}

	function formatMoney(amount) {
		return '$' + amount.toFixed(2);
	}

	function parsePrices(form) {
		try {
			return JSON.parse(form.getAttribute('data-hfo-event-prices') || '{}') || {};
		} catch (error) {
			return {};
		}
	}

	function setupForm(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var prices = parsePrices(form);
		var currentStep = 0;
		var visibleSteps = [];

		function getRegistrationType() {
			return getFieldValue(form, 'registration_type') || 'team';
		}

		function shouldShowStep(step) {
			var registrationType = getRegistrationType();
			var stepType = step.getAttribute('data-hfo-step-type');
			var participant = step.getAttribute('data-hfo-participant-step');

			if ('sponsor_only' === registrationType && ('participant' === stepType || 'guests' === stepType)) {
				return false;
			}

			if ('individual' === registrationType && participant && 'captain' !== participant) {
				return false;
			}

			return true;
		}

		function rebuildVisibleSteps() {
			visibleSteps = steps.filter(shouldShowStep);
		}

		function updateStepLabels() {
			stepLabels.forEach(function (label, labelIndex) {
				var step = steps[labelIndex];
				var visibleIndex = visibleSteps.indexOf(step);
				var isVisible = visibleIndex !== -1;

				label.hidden = !isVisible;
				label.classList.toggle('is-active', isVisible && visibleIndex === currentStep);
				label.classList.toggle('is-complete', isVisible && visibleIndex < currentStep);
			});
		}

		function showStep(index) {
			rebuildVisibleSteps();
			currentStep = Math.max(0, Math.min(index, visibleSteps.length - 1));

			steps.forEach(function (step) {
				step.hidden = visibleSteps[currentStep] !== step;
			});

			updateStepLabels();

			if (backButton) {
				backButton.hidden = currentStep === 0;
			}

			if (nextButton) {
				nextButton.hidden = currentStep === visibleSteps.length - 1;
			}

			updateReview();
		}

		function setReviewValue(key, value) {
			var target = form.querySelector('[data-hfo-review="' + key + '"]');

			if (target) {
				target.textContent = value;
			}
		}

		function calculateReview() {
			var golfQty = 0;
			var lunchQty = getNumberFieldValue(form, 'additional_lunch_count');
			var dinnerQty = getNumberFieldValue(form, 'additional_dinner_count');
			var sponsorshipLevel = getFieldValue(form, 'sponsorship_level');
			var sponsorKey = sponsorshipLevel ? sponsorshipLevel + '_sponsor_price' : '';

			participantSteps.forEach(function (participant) {
				if ('golf' === getFieldValue(form, participant + '_participation_type')) {
					golfQty += 1;
				}
			});

			var subtotal = (golfQty * (parseFloat(prices.golf_price) || 0)) +
				(lunchQty * (parseFloat(prices.lunch_price) || 0)) +
				(dinnerQty * (parseFloat(prices.dinner_price) || 0)) +
				(sponsorKey ? (parseFloat(prices[sponsorKey]) || 0) : 0);

			return {
				registrationType: getRegistrationType(),
				golfQty: golfQty,
				lunchQty: lunchQty,
				dinnerQty: dinnerQty,
				sponsorshipLevel: sponsorshipLevel,
				subtotal: subtotal
			};
		}

		function updateReview() {
			var review = calculateReview();

			setReviewValue('registration_type', registrationTypeLabels[review.registrationType] || review.registrationType || '—');
			setReviewValue('golf_qty', String(review.golfQty));
			setReviewValue('lunch_qty', String(review.lunchQty));
			setReviewValue('dinner_qty', String(review.dinnerQty));
			setReviewValue('sponsorship_level', sponsorLabels[review.sponsorshipLevel] || 'None');
			setReviewValue('subtotal', formatMoney(review.subtotal));
			setReviewValue('discount_amount', formatMoney(0));
			setReviewValue('grand_total', formatMoney(review.subtotal));
		}

		function handleFormChange(event) {
			if (event.target && 'registration_type' === event.target.name) {
				showStep(Math.min(currentStep, visibleSteps.length - 1));
				return;
			}

			updateReview();
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				showStep(currentStep - 1);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				var activeStep = visibleSteps[currentStep];
				var invalidField = activeStep ? activeStep.querySelector(':invalid') : null;

				if (invalidField) {
					invalidField.reportValidity();
					return;
				}

				showStep(currentStep + 1);
			});
		}

		form.addEventListener('input', handleFormChange);
		form.addEventListener('change', handleFormChange);

		showStep(0);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), setupForm);
	});
}());
