export default class OtpInput {
	initialize(naja) {
		const initializeInputs = (root) => {
			root.querySelectorAll('[data-otp-control]').forEach(control => {
				const input = control.querySelector('[data-otp-input]');
				const slots = Array.from(control.querySelectorAll('[data-otp-slot]'));

				if (!input || input.dataset.otpInitialized) {
					return;
				}

				const updateSlots = () => {
					const value = input.value.replace(/\D/g, '').slice(0, slots.length);
					input.value = value;

					slots.forEach((slot, index) => {
						slot.textContent = value[index] ?? '';
						slot.classList.toggle('is-filled', index < value.length);
					});
				};

				input.addEventListener('input', updateSlots);
				input.dataset.otpInitialized = 'true';
				updateSlots();
			});
		};

		initializeInputs(document);
		naja.snippetHandler.addEventListener('afterUpdate', event => {
			initializeInputs(event.detail.snippet);
		});
	}
}
