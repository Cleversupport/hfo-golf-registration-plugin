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
		var keys = ['registration_type'];

		if (registrationType === 'team') {
			keys = keys.concat(['main_contact'], PARTICIPANT_KEYS, ['additional_guests']);
		} else if (registrationType === 'individual') {
			keys = keys.concat(['captain', 'additional_guests']);
		}

		keys.push('sponsorship', 'review');
		return keys;
	}


	function getStepFieldControls(step) {
		return Array.prototype.slice.call(step.querySelectorAll('input, select, textarea'));
	}

	function getFieldStepKey(field) {
		var step = field.closest('[data-hfo-golf-registration-step]');
		return step ? step.dataset.stepKey : '';
	}

	function isControlVisible(field) {
		if (field.disabled || field.type === 'hidden') {
			return false;
		}

		if (field.closest('[hidden]')) {
			return false;
		}

		return !!(field.offsetWidth || field.offsetHeight || field.getClientRects().length);
	}

	function isFieldApplicableForRegistrationType(field, registrationType) {
		var stepKey = getFieldStepKey(field);
		var visibleKeys = getVisibleStepKeys(registrationType);

		return !stepKey || visibleKeys.indexOf(stepKey) !== -1;
	}

	function rememberOriginalRequiredState(field) {
		if (typeof field.dataset.hfoGolfOriginalRequired === 'undefined') {
			field.dataset.hfoGolfOriginalRequired = field.required ? '1' : '0';
		}

		if (field.hasAttribute('aria-required') && typeof field.dataset.hfoGolfOriginalAriaRequired === 'undefined') {
			field.dataset.hfoGolfOriginalAriaRequired = field.getAttribute('aria-required') || '';
		}
	}

	function setFieldRequired(field, required) {
		if (required) {
			field.required = true;

			if (typeof field.dataset.hfoGolfOriginalAriaRequired !== 'undefined') {
				field.setAttribute('aria-required', field.dataset.hfoGolfOriginalAriaRequired || 'true');
			} else if (field.getAttribute('aria-required') === 'false') {
				field.removeAttribute('aria-required');
			}
		} else {
			field.required = false;

			if (typeof field.dataset.hfoGolfOriginalAriaRequired !== 'undefined' || field.hasAttribute('aria-required')) {
				field.setAttribute('aria-required', 'false');
			}
		}
	}

	function updateRequiredFieldsForVisibleSteps(form) {
		var registrationType = getFieldValue(form, 'registration_type') || 'individual';

		Array.prototype.forEach.call(form.querySelectorAll('[data-hfo-golf-registration-step]'), function (step) {
			var stepIsVisible = !step.hidden;

			getStepFieldControls(step).forEach(function (field) {
				rememberOriginalRequiredState(field);

				setFieldRequired(
					field,
					stepIsVisible &&
					field.dataset.hfoGolfOriginalRequired === '1' &&
					isFieldApplicableForRegistrationType(field, registrationType) &&
					isControlVisible(field)
				);
			});
		});
	}

	function focusFirstInvalidVisibleField(form) {
		var invalidFields = Array.prototype.slice.call(form.querySelectorAll('input:invalid, select:invalid, textarea:invalid'));
		var firstInvalidVisibleField = null;

		invalidFields.some(function (field) {
			var step = field.closest('[data-hfo-golf-registration-step]');

			if ((!step || !step.hidden) && isControlVisible(field)) {
				firstInvalidVisibleField = field;
				return true;
			}

			return false;
		});

		if (!firstInvalidVisibleField) {
			return;
		}

		if (typeof firstInvalidVisibleField.scrollIntoView === 'function') {
			firstInvalidVisibleField.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		if (typeof firstInvalidVisibleField.focus === 'function') {
			firstInvalidVisibleField.focus({ preventScroll: true });
		}
	}

	function validateStep(step) {
		var controls = getStepFieldControls(step).filter(isControlVisible);
		var invalidField = null;

		controls.some(function (field) {
			if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
				invalidField = field;
				return true;
			}

			return false;
		});

		if (!invalidField) {
			return true;
		}

		if (typeof invalidField.reportValidity === 'function') {
			invalidField.reportValidity();
		}

		if (typeof invalidField.focus === 'function') {
			invalidField.focus();
		}

		return false;
	}

	function updateSponsorFieldVisibility(form) {
		var sponsorLevel = getFieldValue(form, 'sponsorship_level');
		var teeSponsorSelected = isChecked(form, 'tee_sponsor_selected');
		var showSponsorFields = sponsorLevel !== '' || teeSponsorSelected;
		var sponsorFields = form.querySelector('[data-hfo-golf-sponsor-fields]');

		if (sponsorFields) {
			sponsorFields.hidden = !showSponsorFields;
		}
	}

	function updateDynamicLabels(form, registrationType) {
		var labelType = registrationType === 'individual' ? 'individualLabel' : 'teamLabel';

		Array.prototype.forEach.call(form.querySelectorAll('[data-team-label][data-individual-label]'), function (node) {
			node.textContent = node.dataset[labelType] || node.textContent;
		});
	}

	function calculateReview(form) {
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
			if (isChecked(form, participantKey + '_golf_selected')) {
				golfQty += 1;
			}

			if (isChecked(form, participantKey + '_lunch_selected')) {
				lunchQty += 1;
			}

			if (isChecked(form, participantKey + '_dinner_selected')) {
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

	function copyCaptainToMainContact(form) {
		var fieldMap = {
			main_contact_name: 'captain_name',
			main_contact_email: 'captain_email',
			main_contact_phone: 'captain_phone',
			main_contact_address: 'captain_address',
			main_contact_city: 'captain_city',
			main_contact_state: 'captain_state',
			main_contact_zip: 'captain_zip'
		};

		Object.keys(fieldMap).forEach(function (mainContactFieldName) {
			var mainContactField = getField(form, mainContactFieldName);
			var captainField = getField(form, fieldMap[mainContactFieldName]);

			if (mainContactField && captainField) {
				mainContactField.value = captainField.value;
			}
		});
	}

	function normalizeHiddenParticipantsBeforeSubmit(form) {
		var registrationType = getFieldValue(form, 'registration_type') || 'individual';
		var participantsToClear = [];

		if (registrationType === 'individual') {
			participantsToClear = ['member_2', 'member_3', 'member_4'];
		} else if (registrationType === 'sponsor_only') {
			participantsToClear = PARTICIPANT_KEYS;
		}

		participantsToClear.forEach(function (participantKey) {
			['golf_selected', 'lunch_selected', 'dinner_selected'].forEach(function (selectionKey) {
				var field = getField(form, participantKey + '_' + selectionKey);

				if (field) {
					field.checked = false;
				}
			});
		});
	}

	function setupForm(form) {
		if (form.dataset.hfoGolfRegistrationSetup === '1') {
			updateSponsorFieldVisibility(form);
			updateRequiredFieldsForVisibleSteps(form);
			calculateReview(form);
			return;
		}

		form.dataset.hfoGolfRegistrationSetup = '1';

		var steps = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-step]'));
		var stepLabels = Array.prototype.slice.call(form.querySelectorAll('[data-hfo-golf-registration-steps] li'));
		var backButton = form.querySelector('[data-hfo-golf-registration-back]');
		var nextButton = form.querySelector('[data-hfo-golf-registration-next]');
		var submitButton = form.querySelector('.hfo-golf-registration-submit');
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
			updateSponsorFieldVisibility(form);
			calculateReview(form);

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
				if (currentStepKey === 'review' || visibleIndex === visibleSteps.length - 1) {
					nextButton.hidden = true;
					nextButton.setAttribute('aria-hidden', 'true');
					nextButton.style.display = 'none';
				} else {
					nextButton.hidden = false;
					nextButton.removeAttribute('aria-hidden');
					nextButton.style.display = '';
				}
			}

			if (submitButton) {
				if (currentStepKey === 'review') {
					submitButton.hidden = false;
					submitButton.removeAttribute('aria-hidden');
				} else {
					submitButton.hidden = true;
					submitButton.setAttribute('aria-hidden', 'true');
				}
			}

			updateSponsorFieldVisibility(form);
			updateRequiredFieldsForVisibleSteps(form);
			calculateReview(form);
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				var visibleKeys = getVisibleStepKeys(getRegistrationType());
				showStepByKey(visibleKeys[Math.max(0, visibleKeys.indexOf(currentStepKey) - 1)]);
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				var currentStep = form.querySelector('[data-hfo-golf-registration-step][data-step-key="' + currentStepKey + '"]');

				updateSponsorFieldVisibility(form);
				updateRequiredFieldsForVisibleSteps(form);

				if (currentStep && !validateStep(currentStep)) {
					focusFirstInvalidVisibleField(form);
					return;
				}

				var visibleKeys = getVisibleStepKeys(getRegistrationType());
				showStepByKey(visibleKeys[Math.min(visibleKeys.length - 1, visibleKeys.indexOf(currentStepKey) + 1)]);
			});
		}

		form.addEventListener('input', function () {
			updateSponsorFieldVisibility(form);
			updateRequiredFieldsForVisibleSteps(form);
			calculateReview(form);
		});

		if (submitButton) {
			submitButton.addEventListener('click', function (event) {
				if (getRegistrationType() === 'individual') {
					copyCaptainToMainContact(form);
				}

				normalizeHiddenParticipantsBeforeSubmit(form);
				updateSponsorFieldVisibility(form);
				updateRequiredFieldsForVisibleSteps(form);

				if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
					event.preventDefault();
					focusFirstInvalidVisibleField(form);
				}
			});
		}

		form.addEventListener('submit', function () {
			if (getRegistrationType() === 'individual') {
				copyCaptainToMainContact(form);
			}

			normalizeHiddenParticipantsBeforeSubmit(form);
			updateSponsorFieldVisibility(form);
			updateRequiredFieldsForVisibleSteps(form);
		});

		form.addEventListener('change', function (event) {
			if (event.target && (event.target.name === 'sponsorship_level' || event.target.name === 'tee_sponsor_selected')) {
				updateSponsorFieldVisibility(form);
				updateRequiredFieldsForVisibleSteps(form);
			}

			if (event.target && event.target.name === 'registration_type') {
				showStepByKey(currentStepKey);
				updateRequiredFieldsForVisibleSteps(form);
			}

			calculateReview(form);
		});

		showStepByKey(currentStepKey);
	}

	window.HFOGolfRegistrationInit = function () {
		Array.prototype.forEach.call(document.querySelectorAll('[data-hfo-golf-registration-form]'), function (form) {
			if (form.dataset.hfoGolfRegistrationInitialized === '1') {
				return;
			}

			setupForm(form);
			form.dataset.hfoGolfRegistrationInitialized = '1';
		});
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', window.HFOGolfRegistrationInit);
	} else {
		window.HFOGolfRegistrationInit();
	}

	setTimeout(window.HFOGolfRegistrationInit, 250);
	setTimeout(window.HFOGolfRegistrationInit, 1000);
}());
