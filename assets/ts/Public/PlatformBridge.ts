import { ApiClient, SessionManager } from 'assets/ts/Services';
import { EventBus, Dom, DomNode } from 'assets/ts/Core';
import { FormValidator } from 'assets/ts/Features';
import { MODULE } from 'assets/ts/Const';
import {
	type AiBridgeEventMap,
	type EventCallback,
} from 'assets/ts/Core/EventBus';
import {
	type ApiResult,
	type TransportMode,
	type ValidationError,
} from 'assets/ts/Types';

// ─── Public API Types ─────────────────────────────────

export type NotificationType = 'success' | 'error' | 'warning' | 'info';

export interface FieldOptions {
	/** Triggernout change event po nastavení hodnoty */
	triggerChange?: boolean;
	/** Vymazat validační chyby na poli */
	clearErrors?: boolean;
}

export interface NotificationOptions {
	type?: NotificationType;
	title?: string;
	duration?: number;
}

export interface SubmitOptions {
	/** Vynutit konkrétní transport */
	transport?: TransportMode;
	/** Přepsat formulářová data vlastními */
	overrideData?: Record<string, unknown>;
}

export interface FieldInfo {
	name: string;
	value: string;
	type: string;
	disabled: boolean;
	required: boolean;
	element: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
}

// ─── PlatformBridge Public API ────────────────────────

/**
 * PlatformBridge – Veřejné rozhraní pro cílovou aplikaci.
 *
 * Umožňuje:
 * - Dynamickou manipulaci s formulářovými poli (get/set/enable/disable/show/hide)
 * - Odesílání formuláře programově
 * - Přístup k výsledkům a jejich manipulaci
 * - Napojení na interní event systém (subscribe/unsubscribe/once)
 * - Zobrazování notifikací a loading stavů
 * - Správu session dat
 *
 * @example
 * // Cílová aplikace
 * const pb = window.PlatformBridge;
 *
 * pb.setFieldValue('tone', 'formal');
 * pb.disableField('language');
 * pb.on('success', ({ data }) => console.log(data));
 * pb.submit();
 */
export class PlatformBridge {
	constructor(
		private readonly api: ApiClient,
		private readonly events: EventBus,
		private readonly session: SessionManager,
		private readonly validator: FormValidator,
	) {}

	// ═══════════════════════════════════════════════════
	// ── Field Manipulation ────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Nastaví hodnotu formulářového pole podle jména.
	 * Podporuje input, select, textarea, checkbox i radio.
	 *
	 * @param name Atribut `name` pole
	 * @param value Nová hodnota (pro checkbox: 'true'/'false')
	 * @param options Volitelné nastavení
	 *
	 * @example
	 * pb.setFieldValue('tone', 'formal');
	 * pb.setFieldValue('agree', 'true'); // checkbox
	 */
	setFieldValue(name: string, value: string, options: FieldOptions = {}): this {
		const { triggerChange = true, clearErrors = true } = options;
		const el = this.getFieldElement(name);

		if (!el) {
			console.warn(`[PlatformBridge] Pole "${name}" nenalezeno.`);
			return this;
		}

		if (el instanceof HTMLInputElement) {
			if (el.type === 'checkbox') {
				el.checked = value === 'true' || value === '1';
			} else if (el.type === 'radio') {
				this.setRadioValue(el.form!, name, value);
			} else {
				el.value = value;
			}
		} else {
			el.value = value;
		}

		if (triggerChange) {
			el.dispatchEvent(new Event('change', { bubbles: true }));
			el.dispatchEvent(new Event('input', { bubbles: true }));
		}

		if (clearErrors) {
			this.clearFieldError(name);
		}

		return this;
	}

	/**
	 * Vrátí aktuální hodnotu pole.
	 *
	 * @param name Atribut `name` pole
	 * @returns Hodnota pole, nebo null pokud neexistuje
	 */
	getFieldValue(name: string): string | null {
		const el = this.getFieldElement(name);
		if (!el) return null;

		if (el instanceof HTMLInputElement && el.type === 'checkbox') {
			return el.checked ? 'true' : 'false';
		}

		return el.value;
	}

	/**
	 * Nastaví hodnoty více polí najednou.
	 *
	 * @param values Objekt { name: value }
	 * @param options Volitelné nastavení
	 *
	 * @example
	 * pb.setFieldValues({
	 *     tone: 'formal',
	 *     language: 'cs',
	 *     length: '500'
	 * });
	 */
	setFieldValues(values: Record<string, string>, options?: FieldOptions): this {
		for (const [name, value] of Object.entries(values)) {
			this.setFieldValue(name, value, options);
		}
		return this;
	}

	/**
	 * Vrátí hodnoty všech polí formuláře jako objekt.
	 */
	getFormData(): Record<string, unknown> {
		const form = this.getForm();
		return form ? ApiClient.extractFormData(form) : {};
	}

	/**
	 * Vrátí informace o konkrétním poli.
	 */
	getFieldInfo(name: string): FieldInfo | null {
		const el = this.getFieldElement(name);
		if (!el) return null;

		return {
			name: el.name,
			value: el.value,
			type: el instanceof HTMLInputElement ? el.type : el.tagName.toLowerCase(),
			disabled: el.disabled,
			required: el.required,
			element: el,
		};
	}

	/**
	 * Vrátí seznam všech polí formuláře.
	 */
	getFields(): FieldInfo[] {
		const form = this.getForm();
		if (!form) return [];

		return (Array.from(form.elements) as HTMLElement[])
			.filter((el): el is HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement =>
				(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)
				&& !!el.name
				&& el.type !== 'hidden'
				&& el.type !== 'button'
				&& el.type !== 'submit',
			)
			.map(el => ({
				name: el.name,
				value: el.value,
				type: el instanceof HTMLInputElement ? el.type : el.tagName.toLowerCase(),
				disabled: el.disabled,
				required: el.required,
				element: el,
			}));
	}

	// ═══════════════════════════════════════════════════
	// ── Field State ───────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Zablokuje pole (disabled).
	 */
	disableField(name: string): this {
		const el = this.getFieldElement(name);
		if (el) el.disabled = true;
		return this;
	}

	/**
	 * Odblokuje pole (enabled).
	 */
	enableField(name: string): this {
		const el = this.getFieldElement(name);
		if (el) el.disabled = false;
		return this;
	}

	/**
	 * Zablokuje/odblokuje více polí najednou.
	 *
	 * @example
	 * pb.disableFields(['tone', 'language', 'length']);
	 */
	disableFields(names: string[]): this {
		names.forEach(n => this.disableField(n));
		return this;
	}

	enableFields(names: string[]): this {
		names.forEach(n => this.enableField(n));
		return this;
	}

	/**
	 * Nastaví pole jako povinné (required).
	 */
	setRequired(name: string, required = true): this {
		const el = this.getFieldElement(name);
		if (el) el.required = required;
		return this;
	}

	/**
	 * Skryje blok obsahující dané pole.
	 */
	hideField(name: string): this {
		const wrapper = this.getFieldWrapper(name);
		if (wrapper) wrapper.hide();
		return this;
	}

	/**
	 * Zobrazí blok obsahující dané pole.
	 */
	showField(name: string): this {
		const wrapper = this.getFieldWrapper(name);
		if (wrapper) wrapper.show();
		return this;
	}

	/**
	 * Přepne viditelnost bloku pole.
	 */
	toggleField(name: string, visible?: boolean): this {
		const wrapper = this.getFieldWrapper(name);
		if (!wrapper) return this;

		if (visible === undefined) {
			wrapper.el.style.display === 'none' ? wrapper.show() : wrapper.hide();
		} else {
			visible ? wrapper.show() : wrapper.hide();
		}
		return this;
	}

	// ═══════════════════════════════════════════════════
	// ── Select Options ────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Přidá option do selectu.
	 *
	 * @example
	 * pb.addSelectOption('language', 'pl', 'Polština');
	 */
	addSelectOption(name: string, value: string, label: string, selected = false): this {
		const el = this.getFieldElement(name);
		if (!(el instanceof HTMLSelectElement)) return this;

		const option = new Option(label, value, selected, selected);
		el.add(option);
		el.dispatchEvent(new Event('change', { bubbles: true }));

		return this;
	}

	/**
	 * Odebere option ze selectu podle hodnoty.
	 */
	removeSelectOption(name: string, value: string): this {
		const el = this.getFieldElement(name);
		if (!(el instanceof HTMLSelectElement)) return this;

		const option = el.querySelector<HTMLOptionElement>(`option[value="${value}"]`);
		if (option) {
			option.remove();
			el.dispatchEvent(new Event('change', { bubbles: true }));
		}

		return this;
	}

	/**
	 * Nahradí všechny options v selectu.
	 *
	 * @example
	 * pb.setSelectOptions('language', [
	 *     { value: 'cs', label: 'Čeština' },
	 *     { value: 'en', label: 'Angličtina' },
	 * ]);
	 */
	setSelectOptions(name: string, options: Array<{ value: string; label: string }>, selectedValue?: string): this {
		const el = this.getFieldElement(name);
		if (!(el instanceof HTMLSelectElement)) return this;

		el.innerHTML = '';
		for (const opt of options) {
			const selected = opt.value === selectedValue;
			el.add(new Option(opt.label, opt.value, selected, selected));
		}
		el.dispatchEvent(new Event('change', { bubbles: true }));

		return this;
	}

	// ═══════════════════════════════════════════════════
	// ── Validation ────────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Spustí validaci formuláře.
	 *
	 * @returns true pokud je formulář validní
	 */
	validate(): boolean {
		const form = this.getForm();
		return form ? this.validator.validate(form) : false;
	}

	/**
	 * Vymaže všechny validační chyby z formuláře.
	 */
	clearValidationErrors(): this {
		const form = this.getForm();
		if (form) this.validator.clearErrors(form);
		return this;
	}

	/**
	 * Vymaže chybu u konkrétního pole.
	 */
	clearFieldError(name: string): this {
		const el = this.getFieldElement(name);
		if (!el) return this;

		const wrapper = el.closest(`.${MODULE.FIELD}`);
		wrapper?.classList.remove(MODULE.FIELD_ERROR);

		const errorMsg = el.parentElement?.querySelector(`[data-validation-error="${name}"]`);
		errorMsg?.remove();

		return this;
	}

	/**
	 * Zobrazí vlastní chybovou zprávu u pole.
	 *
	 * @example
	 * pb.setFieldError('email', 'Neplatný formát emailu');
	 */
	setFieldError(name: string, message: string): this {
		this.clearFieldError(name);

		const el = this.getFieldElement(name);
		if (!el) return this;

		const wrapper = el.closest(`.${MODULE.FIELD}`);
		wrapper?.classList.add(MODULE.FIELD_ERROR);

		const errorEl = document.createElement('span');
		errorEl.className = MODULE.ERROR_MSG;
		errorEl.textContent = message;
		errorEl.setAttribute('data-validation-error', name);
		el.insertAdjacentElement('afterend', errorEl);

		return this;
	}

	/**
	 * Zobrazí chyby u více polí najednou.
	 *
	 * @example
	 * pb.setFieldErrors([
	 *     { field: 'email', message: 'Neplatný email' },
	 *     { field: 'name', message: 'Povinné pole' },
	 * ]);
	 */
	setFieldErrors(errors: ValidationError[]): this {
		for (const err of errors) {
			this.setFieldError(err.field, err.message);
		}
		return this;
	}

	// ═══════════════════════════════════════════════════
	// ── Form Submission ───────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Odešle formulář programově.
	 * Provede validaci, extrakci dat a odeslání přes API.
	 *
	 * @example
	 * // Odeslat s výchozím transportem
	 * const result = await pb.submit();
	 *
	 * // Odeslat přes SSE
	 * const result = await pb.submit({ transport: 'sse' });
	 *
	 * // Odeslat s vlastními daty
	 * const result = await pb.submit({ overrideData: { tone: 'creative' } });
	 */
	async submit(options: SubmitOptions = {}): Promise<ApiResult | null> {
		const form = this.getForm();
		if (!form) {
			console.warn('[PlatformBridge] Formulář nenalezen.');
			return null;
		}

		if (!this.validator.validate(form)) {
			return null;
		}

		let data = ApiClient.extractFormData(form);

		if (options.overrideData) {
			data = { ...data, ...options.overrideData };
		}

		const result = options.transport
			? await this.api.via(options.transport).send(data)
			: await this.api.send(data);

		if (result) {
			this.session.save(data);
		}

		return result;
	}

	/**
	 * Přeruší probíhající API požadavek.
	 */
	abort(): this {
		this.api.abort();
		return this;
	}

	/**
	 * Vrací true pokud probíhá SSE streaming.
	 */
	get isStreaming(): boolean {
		return this.api.isStreaming;
	}

	// ═══════════════════════════════════════════════════
	// ── Events ────────────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Přihlásí callback na interní event.
	 *
	 * @example
	 * pb.on('success', ({ data, duration }) => {
	 *     console.log('Vygenerováno za', duration, 'ms');
	 * });
	 *
	 * pb.on('error', ({ error }) => {
	 *     alert(error.message);
	 * });
	 *
	 * pb.on('sse:progress', ({ phase, current, total }) => {
	 *     console.log(`${phase}: ${current}/${total}`);
	 * });
	 */
	on<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		this.events.subscribe(event, callback);
		return this;
	}

	/**
	 * Odhlásí callback z interního eventu.
	 */
	off<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		this.events.unsubscribe(event, callback);
		return this;
	}

	/**
	 * Jednorázový event listener.
	 */
	once<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		this.events.once(event, callback);
		return this;
	}

	/**
	 * Emituje event (pro pokročilé integrace).
	 */
	emit<K extends keyof AiBridgeEventMap>(event: K, payload: AiBridgeEventMap[K]): this {
		this.events.publish(event, payload);
		return this;
	}

	// ═══════════════════════════════════════════════════
	// ── Result Manipulation ───────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Vrátí HTML obsah výsledkového kontejneru.
	 */
	getResultHtml(): string {
		const container = this.getResultContainer();
		return container?.html() ?? '';
	}

	/**
	 * Nastaví HTML obsah výsledkového kontejneru.
	 */
	setResultHtml(html: string): this {
		const container = this.getResultContainer();
		if (container) container.html(html);
		return this;
	}

	/**
	 * Vymaže výsledky.
	 */
	clearResults(): this {
		return this.setResultHtml('');
	}

	/**
	 * Zjistí, zda jsou zobrazeny výsledky.
	 */
	hasResults(): boolean {
		const html = this.getResultHtml();
		return html.trim().length > 0;
	}

	/**
	 * Vrátí text konkrétního výsledku podle klíče (data-key).
	 *
	 * @example
	 * const subject = pb.getResultByKey('subject');
	 */
	getResultByKey(key: string): string | null {
		const container = this.getResultContainer();
		if (!container) return null;

		const item = Dom.q(`[data-key="${key}"]`, container.el);
		if (!item) return null;

		const content = item.querySelector('[data-flag="result-content"]');
		return content?.textContent ?? null;
	}

	/**
	 * Vrátí všechny výsledky jako objekt { key: text }.
	 */
	getResults(): Record<string, string> {
		const container = this.getResultContainer();
		if (!container) return {};

		const items = Dom.qa('[data-key]', container.el);
		const results: Record<string, string> = {};

		for (const item of items) {
			const key = item.dataset.key;
			if (!key) continue;
			const content = item.querySelector('[data-flag="result-content"]');
			results[key] = content?.textContent ?? '';
		}

		return results;
	}

	// ═══════════════════════════════════════════════════
	// ── Session ───────────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Vrátí data z poslední relace (session).
	 */
	getSessionData(): Record<string, unknown> | null {
		return this.session.getFormData();
	}

	/**
	 * Zjistí, zda existuje aktivní relace.
	 */
	hasSession(): boolean {
		return this.session.hasSession();
	}

	/**
	 * Vymaže relaci.
	 */
	clearSession(): this {
		this.session.clear();
		return this;
	}

	// ═══════════════════════════════════════════════════
	// ── Form Reset ────────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Resetuje formulář do výchozího stavu.
	 * Vymaže validační chyby a volitelně i výsledky.
	 */
	reset(options: { clearResults?: boolean } = {}): this {
		const form = this.getForm();
		if (form) {
			form.reset();
			this.validator.clearErrors(form);

			// Triggernout change na selectech pro sync CustomSelectu
			Dom.qa('select', form).forEach(sel => {
				sel.dispatchEvent(new Event('change', { bubbles: true }));
			});
		}

		if (options.clearResults ?? true) {
			this.clearResults();
		}

		return this;
	}

	/**
	 * Naplní formulář daty z objektu.
	 *
	 * @example
	 * pb.fillForm({
	 *     tone: 'formal',
	 *     language: 'cs',
	 *     length: '500',
	 *     agree: 'true',
	 * });
	 */
	fillForm(data: Record<string, string>): this {
		return this.setFieldValues(data, { triggerChange: true, clearErrors: true });
	}

	// ═══════════════════════════════════════════════════
	// ── UI Utilities ──────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Fokusuje dané pole.
	 */
	focusField(name: string): this {
		const el = this.getFieldElement(name);
		el?.focus();
		return this;
	}

	/**
	 * Scrolluje na formulář / výsledky.
	 */
	scrollTo(target: 'form' | 'results' | 'top'): this {
		let el: HTMLElement | null = null;

		switch (target) {
			case 'form':
				el = this.getForm();
				break;
			case 'results':
				el = this.getResultContainer()?.el ?? null;
				break;
			case 'top':
				el = document.querySelector<HTMLElement>(`.${MODULE.ROOT}`);
				break;
		}

		el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
		return this;
	}

	/**
	 * Vrátí root element modulu (.pb-module).
	 */
	getRoot(): HTMLElement | null {
		return document.querySelector<HTMLElement>(`.${MODULE.ROOT}`);
	}

	// ═══════════════════════════════════════════════════
	// ── Destroy ───────────────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Odpojí public API a vyčistí reference.
	 * Volatelné při odebrání modulu ze stránky.
	 */
	destroy(): void {
		this.events.clear();
		this.session.clear();
		(window as any).PlatformBridge = undefined;
	}

	// ═══════════════════════════════════════════════════
	// ── Private Helpers ───────────────────────────────
	// ═══════════════════════════════════════════════════

	/**
	 * Najde formulářový element (input/select/textarea) podle `name`.
	 */
	private getFieldElement(name: string): HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null {
		const form = this.getForm();
		if (!form) return null;

		const el = form.querySelector<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(`[name="${name}"]`);
		return el;
	}

	/**
	 * Najde wrapper (.pb-module__field) pole podle name.
	 */
	private getFieldWrapper(name: string): DomNode | null {
		const el = this.getFieldElement(name);
		if (!el) return null;

		const wrapper = el.closest<HTMLElement>(`.${MODULE.FIELD}`);
		return wrapper ? Dom.wrap(wrapper) : null;
	}

	/**
	 * Vrátí formulář z DOM.
	 */
	private getForm(): HTMLFormElement | null {
		return document.querySelector<HTMLFormElement>('form');
	}

	/**
	 * Vrátí result kontejner.
	 */
	private getResultContainer(): DomNode | null {
		const el = document.querySelector<HTMLElement>('[data-component="pb-result"]');
		return el ? Dom.wrap(el) : null;
	}

	/**
	 * Nastaví hodnotu radio skupiny.
	 */
	private setRadioValue(form: HTMLFormElement, name: string, value: string): void {
		const radios = form.querySelectorAll<HTMLInputElement>(`input[name="${name}"]`);
		for (const radio of radios) {
			radio.checked = radio.value === value;
		}
	}
}