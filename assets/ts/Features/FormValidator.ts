import { EventBus } from 'assets/ts/Core';
import { type ValidationError } from 'assets/ts/Types';

/**
 * FormValidator - Validace formulářových prvků (empty check)
 *
 * Validuje:
 * - Prázdné textové inputy, textareas, selecty
 * - Nezaškrtnuté checkboxy
 * - Podpora pro required atribut i vlastní pravidla
 * - Vizuální zvýraznění chybných polí
 */

export interface ValidatorOptions {
	/** CSS třída přidaná na field wrapper při chybě */
	errorClass?: string;
	/** CSS třída pro error zprávu */
	errorMessageClass?: string;
	/** Selektor pro wrapper kolem fieldu (pro přidání errorClass) */
	fieldWrapperSelector?: string;
	/** Validovat pouze pole s atributem required? */
	requiredOnly?: boolean;
	/** Vlastní chybové zprávy podle field name */
	messages?: Record<string, string>;
	/** Výchozí chybová zpráva */
	defaultMessage?: string;
}

const DEFAULTS: Required<ValidatorOptions> = {
	errorClass: 'ai-module__field--error',
	errorMessageClass: 'ai-module__error',
	fieldWrapperSelector: '.ai-module__field',
	requiredOnly: true,
	messages: {},
	defaultMessage: 'Toto pole je povinné',
};

export class FormValidator {
	private readonly options: Required<ValidatorOptions>;
	private readonly events: EventBus;

	constructor(events: EventBus, options: ValidatorOptions = {}) {
		this.events = events;
		this.options = { ...DEFAULTS, ...options };
	}

	// ─── Hlavní validace ───────────────────────────────────────────

	/**
	 * Zvaliduje celý formulář.
	 * Vrací true pokud je vše OK, false pokud jsou chyby.
	 */
	validate(form: HTMLFormElement): boolean {
		this.clearErrors(form);

		const errors = this.collectErrors(form);

		if (errors.length > 0) {
			this.showErrors(form, errors);
			this.events.publish('validation', { errors });
			return false;
		}

		return true;
	}

	// ─── Sběr chyb ────────────────────────────────────────────────

	/**
	 * Projde formulářové prvky a vrátí seznam chyb
	 */
	private collectErrors(form: HTMLFormElement): ValidationError[] {
		const errors: ValidationError[] = [];
		const elements = Array.from(form.elements) as HTMLElement[];

		for (const el of elements) {
			if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) {
				continue;
			}

			// Přeskočit disabled, hidden, buttons
			if (el.disabled || el.type === 'hidden' || el.type === 'button' || el.type === 'submit') {
				continue;
			}

			// Pokud requiredOnly, validuj jen pole s required
			if (this.options.requiredOnly && !el.required) {
				continue;
			}

			if (!el.name) {
				continue;
			}

			if (this.isEmpty(el)) {
				errors.push({
					field: el.name,
					message: this.getErrorMessage(el.name),
				});
			}
		}

		return errors;
	}

	/**
	 * Kontrola, zda je prvek prázdný
	 */
	private isEmpty(el: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement): boolean {
 		switch (el.type) {
			case 'checkbox':
				return !(el as HTMLInputElement).checked;

			case 'radio':
				// Radio - zkontroluj, zda je alespoň jeden ze skupiny zaškrtnutý
				const form = el.closest('form');
				if (!form) return !((el as HTMLInputElement).checked);
				const radios = form.querySelectorAll<HTMLInputElement>(`input[name="${el.name}"]`);
				return !Array.from(radios).some(r => r.checked);

			case 'select-one':
			case 'select-multiple':
				return !el.value || el.value === '';

			default:
				return el.value.trim() === '';
		}
	}

	// ─── Zobrazení / vymazání chyb ─────────────────────────────────

	/**
	 * Zobrazí chyby u příslušných polí
	 */
	private showErrors(form: HTMLFormElement, errors: ValidationError[]): void {
		for (const error of errors) {
			const field = form.querySelector<HTMLElement>(`[name="${error.field}"]`);
			if (!field) continue;

			// Najdi wrapper
			const wrapper = field.closest(this.options.fieldWrapperSelector) || field.parentElement;
			if (wrapper) {
				wrapper.classList.add(this.options.errorClass);
			}

			// Vytvoř error zprávu
			const errorEl = document.createElement('span');
			errorEl.className = this.options.errorMessageClass;
			errorEl.textContent = error.message;
			errorEl.setAttribute('data-validation-error', error.field);

			// Vlož za field
			field.insertAdjacentElement('afterend', errorEl);
		}

		// Focus na první chybný field
		const firstError = errors[0];
		if (firstError) {
			const firstField = form.querySelector<HTMLElement>(`[name="${firstError.field}"]`);
			firstField?.focus();
		}
	}

	/**
	 * Vymaže všechny validační chyby z formuláře
	 */
	clearErrors(form: HTMLFormElement): void {
		// Odstraň error třídy
		form.querySelectorAll(`.${this.options.errorClass}`).forEach(el => {
			el.classList.remove(this.options.errorClass);
		});

		// Odstraň error zprávy
		form.querySelectorAll(`[data-validation-error]`).forEach(el => {
			el.remove();
		});
	}

	/**
	 * Vrátí chybovou zprávu pro pole
	 */
	private getErrorMessage(fieldName: string): string {
		return this.options.messages[fieldName] || this.options.defaultMessage;
	}

	/**
	 * Nastaví real-time validaci (odstraní error při vyplnění)
	 */
	enableLiveValidation(form: HTMLFormElement): void {
		form.addEventListener('input', (e: Event) => {
			const target = e.target as HTMLElement;
			if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
				return;
			}

			if (!this.isEmpty(target)) {
				const wrapper = target.closest(this.options.fieldWrapperSelector) || target.parentElement;
				wrapper?.classList.remove(this.options.errorClass);

				const errorMsg = form.querySelector(`[data-validation-error="${target.name}"]`);
				errorMsg?.remove();
			}
		});

		form.addEventListener('change', (e: Event) => {
			const target = e.target as HTMLElement;
			if (target instanceof HTMLSelectElement || (target instanceof HTMLInputElement && (target.type === 'checkbox' || target.type === 'radio'))) {
				if (!this.isEmpty(target)) {
					const wrapper = target.closest(this.options.fieldWrapperSelector) || target.parentElement;
					wrapper?.classList.remove(this.options.errorClass);

					const errorMsg = form.querySelector(`[data-validation-error="${target.name}"]`);
					errorMsg?.remove();
				}
			}
		});
	}
}

// validate(form: HTMLFormElement): boolean {
//   this.clearErrors(form);

//   const errors = this.collectErrors(form);

//   if (errors.length) {
//     this.showErrors(form, errors);
//     return false;
//   }

//   return true;
// }