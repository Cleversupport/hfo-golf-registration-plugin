(function () {
	'use strict';

	function setupForm(form) {
		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var currentStep = 0;

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
				showStep(currentStep + 1);
			});
		}

		showStep(0);
	}

	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), setupForm);
	});
}());
