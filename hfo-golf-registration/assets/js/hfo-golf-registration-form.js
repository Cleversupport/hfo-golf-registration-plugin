(function () {
	'use strict';

	var participantKeysByRegistrationType = {
		team: ['captain', 'member_2', 'member_3', 'member_4'],
		individual: ['captain'],
		sponsor_only: []
	};

	function setupForm(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var participantSteps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-participant]'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var message = form.querySelector('[data-hfo-golf-registration-message]');
		var currentStep = 0;

		function getField(name) {
			return form.querySelector('[name="' + name + '"]');
		}

		function getFieldValue(name) {
			var field = getField(name);
			return field ? field.value : '';
		}

		function getRegistrationType() {
			return getFieldValue('registration_type') || 'individual';
		}

		function getVisibleParticipantKeys() {
			return participantKeysByRegistrationType[getRegistrationType()] || participantKeysByRegistrationType.individual;
		}

		function isStepVisible(step) {
			var conditional = step.getAttribute('data-hfo-golf-registration-conditional');
			var registrationType = getRegistrationType();

			if ('participant' === conditional) {
				return getVisibleParticipantKeys().indexOf(step.getAttribute('data-hfo-golf-registration-participant-step')) !== -1;
			}

			if ('guests' === conditional) {
				return 'sponsor_only' !== registrationType;
			}

			return true;
		}

		function getVisibleSteps() {
			return steps.filter(isStepVisible);
		}

		function setMessage(text) {
			if (!message) {
				return;
			}

			message.textContent = text || '';
			message.hidden = !text;
		}

		function getNumber(name) {
			var value = parseInt(getFieldValue(name), 10);
			return isNaN(value) || value < 0 ? 0 : value;
		}

		function getPrice(key) {
			var value = parseFloat(form.getAttribute('data-' + key) || '0');
			return isNaN(value) ? 0 : value;
		}

		function formatCurrency(amount) {
			return '$' + amount.toFixed(2);
		}

		function setReviewField(name, value) {
			var field = form.querySelector('[data-hfo-golf-registration-review-field="' + name + '"]');

			if (field) {
				field.textContent = value;
			}
		}

		function getSponsorPrice(sponsorshipLevel) {
			if (!sponsorshipLevel) {
				return 0;
			}

			return getPrice(sponsorshipLevel.replace(/_/g, '-') + '-sponsor-price');
		}

		function calculateReview() {
			var registrationType = getRegistrationType();
			var visibleParticipantKeys = getVisibleParticipantKeys();
			var golfQty = 0;
			var lunchQty = 'sponsor_only' === registrationType ? 0 : getNumber('additional_lunch_count');
			var dinnerQty = 'sponsor_only' === registrationType ? 0 : getNumber('additional_dinner_count');
			var sponsorshipLevel = getFieldValue('sponsorship_level');
			var sponsorPrice = getSponsorPrice(sponsorshipLevel);
			var subtotal;

			participantSteps.forEach(function (participantStep) {
				var participantKey = participantStep.getAttribute('data-hfo-golf-registration-participant');
				var participationType;

				if (visibleParticipantKeys.indexOf(participantKey) === -1) {
					return;
				}

				participationType = getFieldValue(participantKey + '_participation_type');

				if ('golf' === participationType) {
					golfQty++;
				}
			});

			subtotal = (golfQty * getPrice('golf-price')) + (lunchQty * getPrice('lunch-price')) + (dinnerQty * getPrice('dinner-price')) + sponsorPrice;

			setReviewField('golf_qty', String(golfQty));
			setReviewField('lunch_qty', String(lunchQty));
			setReviewField('dinner_qty', String(dinnerQty));
			setReviewField('sponsor_level', sponsorshipLevel || 'None');
			setReviewField('subtotal', formatCurrency(subtotal));
			setReviewField('discount_amount', formatCurrency(0));
			setReviewField('grand_total', formatCurrency(subtotal));

			return {
				golfQty: golfQty,
				lunchQty: lunchQty,
				dinnerQty: dinnerQty,
				sponsorshipLevel: sponsorshipLevel,
				subtotal: subtotal
			};
		}

		function updateConditionalVisibility() {
			var visibleParticipantKeys = getVisibleParticipantKeys();
			var visibleSteps = getVisibleSteps();

			participantSteps.forEach(function (participantStep) {
				var participantKey = participantStep.getAttribute('data-hfo-golf-registration-participant');
				participantStep.hidden = visibleParticipantKeys.indexOf(participantKey) === -1;
			});

			steps.forEach(function (step) {
				if (visibleSteps.indexOf(step) === -1) {
					step.hidden = true;
				}
			});

			if (visibleSteps.indexOf(steps[currentStep]) === -1) {
				currentStep = steps.indexOf(visibleSteps.filter(function (step) {
					return steps.indexOf(step) >= currentStep;
				})[0] || visibleSteps[visibleSteps.length - 1]);
			}
		}

		function showStep(index) {
			var visibleSteps = getVisibleSteps();
			var visibleIndex = Math.max(0, Math.min(index, visibleSteps.length - 1));
			var activeStep = visibleSteps[visibleIndex];

			currentStep = steps.indexOf(activeStep);

			steps.forEach(function (step) {
				step.hidden = step !== activeStep;
			});

			stepLabels.forEach(function (label, labelIndex) {
				var labelStep = steps[labelIndex];
				var labelVisibleIndex = visibleSteps.indexOf(labelStep);
				var isVisible = labelVisibleIndex !== -1;

				label.hidden = !isVisible;
				label.classList.toggle('is-active', labelStep === activeStep);
				label.classList.toggle('is-complete', isVisible && labelVisibleIndex < visibleIndex);
			});

			if (backButton) {
				backButton.hidden = visibleIndex === 0;
			}

			if (nextButton) {
				nextButton.hidden = visibleIndex === visibleSteps.length - 1;
			}

			calculateReview();
		}

		function showRelativeStep(offset) {
			var visibleSteps = getVisibleSteps();
			var visibleIndex = visibleSteps.indexOf(steps[currentStep]);

			showStep(visibleIndex + offset);
		}

		function validateCheckout(event) {
			var totals = calculateReview();
			var hasCheckoutItem = totals.golfQty > 0 || totals.lunchQty > 0 || totals.dinnerQty > 0 || '' !== totals.sponsorshipLevel;

			if ('sponsor_only' === getRegistrationType() && '' === totals.sponsorshipLevel) {
				event.preventDefault();
				setMessage('Please select a sponsorship level for sponsor-only registration.');
				showStep(getVisibleSteps().indexOf(form.querySelector('[name="sponsorship_level"]').closest('[data-hfo-golf-registration-step]')));
				return;
			}

			if (!hasCheckoutItem) {
				event.preventDefault();
				setMessage('Please add at least one golfer, guest, or sponsorship before checkout.');
				showStep(getVisibleSteps().length - 1);
				return;
			}

			setMessage('');
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				showRelativeStep(-1);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				showRelativeStep(1);
			});
		}

		form.addEventListener('input', calculateReview);
		form.addEventListener('change', function () {
			updateConditionalVisibility();
			showStep(getVisibleSteps().indexOf(steps[currentStep]));
		});
		form.addEventListener('submit', validateCheckout);

		updateConditionalVisibility();
		showStep(0);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), setupForm);
	});
}());
