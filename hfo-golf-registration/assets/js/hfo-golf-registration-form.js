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
		sponsor_only: 'Sponsor Only',
		additional_guests: 'Additional Guests'
	};
	var OPTIONAL_FIELD_NAMES = [
		'additional_lunch_count',
		'additional_dinner_count',
		'additional_guests_details'
	];

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
			keys = keys.concat(['captain', 'additional_guests', 'sponsorship']);
		} else if (registrationType === 'sponsor_only') {
			keys = keys.concat(['sponsorship', 'additional_guests']);
		} else if (registrationType === 'additional_guests') {
			keys = keys.concat(['main_contact', 'additional_guests']);
		}

		keys.push('review');
		return keys;
	}

	function scrollToFormTop(form) {
		var formWrapper = form.closest('.hfo-golf-registration-form') || form.closest('[data-hfo-golf-registration-form]') || form;

		if (formWrapper && typeof formWrapper.scrollIntoView === 'function') {
			formWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function getStepFieldControls(step) {
		return Array.prototype.slice.call(step.querySelectorAll('input, select, textarea'));
	}

	function isControlVisible(control) {
		if (control.disabled || control.type === 'hidden') {
			return false;
		}

		if (control.closest('[hidden]')) {
			return false;
		}

		return control.getClientRects().length > 0;
	}

	function isCheckboxOrRadio(field) {
		return field.type === 'checkbox' || field.type === 'radio';
	}

	function isOptionalField(field) {
		return OPTIONAL_FIELD_NAMES.indexOf(field.name) !== -1;
	}

	function shouldSkipGenericRequired(field, form) {
		if (field.name === 'sponsorship_level') {
			return getRegistrationSponsorRequirementField(form) === field;
		}

		return false;
	}

	function getRegistrationSponsorRequirementField(form) {
		return getField(form, 'sponsorship_level');
	}

	function updateAdditionalGuestsCustomValidity(form) {
		var details = getField(form, 'additional_guests_details');
		var lunchCount = getField(form, 'additional_lunch_count');
		var dinnerCount = getField(form, 'additional_dinner_count');
		var requiresGuests = getFieldValue(form, 'registration_type') === 'additional_guests';
		var hasGuestCount = getNumericFieldValue(form, 'additional_lunch_count') + getNumericFieldValue(form, 'additional_dinner_count') > 0;

		[details, lunchCount, dinnerCount].forEach(function (field) {
			if (field && typeof field.setCustomValidity === 'function') {
				field.setCustomValidity('');
			}
		});

		if (!requiresGuests) {
			return;
		}

		if (details && typeof details.setCustomValidity === 'function' && isControlVisible(details) && details.value.trim() === '') {
			details.setCustomValidity('Please enter additional guest details.');
		}

		if (!hasGuestCount) {
			[lunchCount, dinnerCount].forEach(function (field) {
				if (field && typeof field.setCustomValidity === 'function' && isControlVisible(field)) {
					field.setCustomValidity('Please enter at least one additional lunch or dinner guest.');
				}
			});
		}
	}

	function updateSponsorOnlyCustomValidity(form) {
		var sponsorshipLevel = getRegistrationSponsorRequirementField(form);

		if (!sponsorshipLevel || typeof sponsorshipLevel.setCustomValidity !== 'function') {
			return;
		}

		if (isControlVisible(sponsorshipLevel) && getFieldValue(form, 'registration_type') === 'sponsor_only' && getFieldValue(form, 'sponsorship_level') === '' && !isChecked(form, 'tee_sponsor_selected')) {
			sponsorshipLevel.setCustomValidity('Please select a Sponsorship Level or add a Tee Sponsor.');
			return;
		}

		sponsorshipLevel.setCustomValidity('');
	}

	function setFieldRequired(field, required) {
		field.required = required;
		field.setAttribute('aria-required', required ? 'true' : 'false');
	}

	function updateRequiredFieldsForVisibleControls(form) {
		Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (field) {
			if (!isControlVisible(field) || isCheckboxOrRadio(field) || isOptionalField(field) || shouldSkipGenericRequired(field, form)) {
				setFieldRequired(field, false);
				return;
			}

			setFieldRequired(field, true);
		});

		updateSponsorOnlyCustomValidity(form);
		updateAdditionalGuestsCustomValidity(form);
	}

	function validateCurrentStep(form, currentStepKey) {
		var currentStep = form.querySelector('[data-hfo-golf-registration-step][data-step-key="' + currentStepKey + '"]');

		updateRequiredFieldsForVisibleControls(form);

		if (!currentStep || currentStep.hidden) {
			return true;
		}

		var controls = getStepFieldControls(currentStep).filter(function (field) {
			return isControlVisible(field) && !isCheckboxOrRadio(field);
		});
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
		var sponsorLevel = registrationType === 'additional_guests' ? '' : getFieldValue(form, 'sponsorship_level');
		var teeSponsorSelected = registrationType !== 'additional_guests' && isChecked(form, 'tee_sponsor_selected');
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
		var playerLunchQty = 0;
		var playerDinnerQty = 0;
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
				playerLunchQty += 1;
			}

			if (isChecked(form, participantKey + '_dinner_selected')) {
				playerDinnerQty += 1;
			}
		});

		var additionalLunchQty = getNumericFieldValue(form, 'additional_lunch_count');
		var additionalDinnerQty = getNumericFieldValue(form, 'additional_dinner_count');

		var sponsorLevel = registrationType === 'additional_guests' ? '' : getFieldValue(form, 'sponsorship_level');
		var teeSponsorSelected = registrationType !== 'additional_guests' && isChecked(form, 'tee_sponsor_selected');
		var subtotal = (golfQty * getPrice(form, 'golfPrice')) +
			(additionalLunchQty * getPrice(form, 'lunchPrice')) +
			(additionalDinnerQty * getPrice(form, 'dinnerPrice'));

		if (sponsorLevel) {
			subtotal += getPrice(form, sponsorLevel + 'SponsorPrice');
		}

		if (teeSponsorSelected) {
			subtotal += getPrice(form, 'teeSponsorPrice');
		}

		setSummary(form, 'registration_type', REGISTRATION_LABELS[registrationType] || registrationType);
		setSummary(form, 'golf_qty', String(golfQty));
		setSummary(form, 'player_lunch_attendance', String(playerLunchQty));
		setSummary(form, 'player_dinner_attendance', String(playerDinnerQty));
		setSummary(form, 'additional_lunch_count', String(additionalLunchQty));
		setSummary(form, 'additional_dinner_count', String(additionalDinnerQty));
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
		} else if (registrationType === 'sponsor_only' || registrationType === 'additional_guests') {
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
			updateRequiredFieldsForVisibleControls(form);
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
			var previousStepKey = currentStepKey;

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
			updateRequiredFieldsForVisibleControls(form);
			calculateReview(form);

			return previousStepKey !== currentStepKey;
		}

		if (backButton) {
			backButton.addEventListener('click', function () {
				var visibleKeys = getVisibleStepKeys(getRegistrationType());

				if (showStepByKey(visibleKeys[Math.max(0, visibleKeys.indexOf(currentStepKey) - 1)])) {
					scrollToFormTop(form);
				}
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				updateSponsorFieldVisibility(form);
				updateRequiredFieldsForVisibleControls(form);

				if (!validateCurrentStep(form, currentStepKey)) {
					return;
				}

				var visibleKeys = getVisibleStepKeys(getRegistrationType());

				if (showStepByKey(visibleKeys[Math.min(visibleKeys.length - 1, visibleKeys.indexOf(currentStepKey) + 1)])) {
					scrollToFormTop(form);
				}
			});
		}

		form.addEventListener('input', function () {
			updateSponsorFieldVisibility(form);
			updateRequiredFieldsForVisibleControls(form);
			calculateReview(form);
		});

		if (submitButton) {
			submitButton.addEventListener('click', function (event) {
				if (getRegistrationType() === 'individual') {
					copyCaptainToMainContact(form);
				}

				normalizeHiddenParticipantsBeforeSubmit(form);
				updateSponsorFieldVisibility(form);
				updateRequiredFieldsForVisibleControls(form);

				if (!validateCurrentStep(form, currentStepKey)) {
					event.preventDefault();
				}
			});
		}

		form.addEventListener('submit', function () {
			if (getRegistrationType() === 'individual') {
				copyCaptainToMainContact(form);
			}

			normalizeHiddenParticipantsBeforeSubmit(form);
			updateSponsorFieldVisibility(form);
			updateRequiredFieldsForVisibleControls(form);
		});

		form.addEventListener('change', function (event) {
			if (event.target && (event.target.name === 'sponsorship_level' || event.target.name === 'tee_sponsor_selected')) {
				updateSponsorFieldVisibility(form);
				updateRequiredFieldsForVisibleControls(form);
			}

			if (event.target && event.target.name === 'registration_type') {
				if (showStepByKey(currentStepKey)) {
					scrollToFormTop(form);
				}

				updateRequiredFieldsForVisibleControls(form);
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
