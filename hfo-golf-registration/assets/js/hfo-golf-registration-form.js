(function () {
	'use strict';

	function setupForm(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var registrationTypeField = form.querySelector('[name="registration_type"]');
		var sponsorshipLevelField = form.querySelector('[name="sponsorship_level"]');
		var sponsorshipTitle = form.querySelector('[data-hfo-golf-registration-sponsorship-title]');
		var sponsorshipHelp = form.querySelector('[data-hfo-golf-registration-sponsorship-help]');
		var sponsorshipStepLabel = form.querySelector('[data-hfo-golf-registration-sponsorship-step-label]');
		var sponsorshipError = form.querySelector('[data-hfo-golf-registration-sponsorship-error]');
		var sponsorOnlyMessage = 'Please select a sponsorship level to continue.';
		var currentStep = 0;

		function getRegistrationType() {
			return registrationTypeField ? registrationTypeField.value : '';
		}

		function setElementText(element, text) {
			if (element && text) {
				element.textContent = text;
			}
		}

		function getDatasetValue(element, key) {
			return element && element.dataset ? element.dataset[key] : '';
		}

		function setSponsorshipError(message) {
			if (!sponsorshipError) {
				return;
			}

			sponsorshipError.textContent = message;
			sponsorshipError.hidden = !message;
		}

		function updateSponsorshipState() {
			var isSponsorOnly = getRegistrationType() === 'sponsor_only';

			if (sponsorshipLevelField) {
				sponsorshipLevelField.required = isSponsorOnly;
				sponsorshipLevelField.setCustomValidity(isSponsorOnly && !sponsorshipLevelField.value ? sponsorOnlyMessage : '');
			}

			setElementText(sponsorshipTitle, isSponsorOnly ? getDatasetValue(sponsorshipTitle, 'requiredTitle') : getDatasetValue(sponsorshipTitle, 'optionalTitle'));
			setElementText(sponsorshipHelp, isSponsorOnly ? getDatasetValue(sponsorshipHelp, 'requiredText') : getDatasetValue(sponsorshipHelp, 'optionalText'));
			setElementText(sponsorshipStepLabel, isSponsorOnly ? getDatasetValue(sponsorshipStepLabel, 'requiredLabel') : getDatasetValue(sponsorshipStepLabel, 'optionalLabel'));

			if (!isSponsorOnly || (sponsorshipLevelField && sponsorshipLevelField.value)) {
				setSponsorshipError('');
			}
		}

		function validateCurrentStep() {
			var visibleControls = Array.prototype.slice.call(steps[currentStep].querySelectorAll('input, select, textarea'));
			var invalidControl = null;

			visibleControls.some(function (control) {
				if (!control.checkValidity()) {
					invalidControl = control;
					return true;
				}

				return false;
			});

			if (invalidControl) {
				if (invalidControl === sponsorshipLevelField) {
					setSponsorshipError(invalidControl.validationMessage || sponsorOnlyMessage);
				}

				invalidControl.reportValidity();
				return false;
			}

			setSponsorshipError('');
			return true;
		}

		function showStep(index) {
			currentStep = Math.max(0, Math.min(index, steps.length - 1));

			steps.forEach(function (step, stepIndex) {
				step.hidden = stepIndex !== currentStep;
			});

			stepLabels.forEach(function (label, labelIndex) {
				label.classList.toggle('is-active', labelIndex === currentStep);
				label.classList.toggle('is-complete', labelIndex < currentStep);
			});

			if (backButton) {
				backButton.hidden = currentStep === 0;
			}

			if (nextButton) {
				nextButton.hidden = currentStep === steps.length - 1;
			}
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				showStep(currentStep - 1);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				updateSponsorshipState();

				if (validateCurrentStep()) {
					showStep(currentStep + 1);
				}
			});
		}

		if (registrationTypeField) {
			registrationTypeField.addEventListener('change', updateSponsorshipState);
		}

		if (sponsorshipLevelField) {
			sponsorshipLevelField.addEventListener('change', updateSponsorshipState);
		}

		form.addEventListener('submit', function (event) {
			updateSponsorshipState();

			if (!form.checkValidity()) {
				event.preventDefault();

				if (sponsorshipLevelField && !sponsorshipLevelField.checkValidity()) {
					setSponsorshipError(sponsorshipLevelField.validationMessage || sponsorOnlyMessage);
				}

				form.reportValidity();
			}
		});

		updateSponsorshipState();
		showStep(0);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), setupForm);
	});
}());
