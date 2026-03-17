/**
 * VisibilityController – Podmíněné zobrazování bloků formuláře.
 *
 * Čte data-visible-if atribut z .pb-module__block wrapperů,
 * parsuje JSON podmínky a na základě aktuální hodnoty zdrojového
 * pole (radio, checkbox) zobrazí nebo skryje blok.
 *
 * Podporované typy zdrojových polí:
 * - radio:    blok se zobrazí, pokud vybraná hodnota === očekávaná hodnota
 * - checkbox: blok se zobrazí, pokud stav checked === očekávaná hodnota (true/false)
 *
 * Příklad data atributu (generuje PHP LayoutManager):
 *   data-visible-if='{"topic_source":"custom"}'
 *
 * Význam: Tento blok se zobrazí pouze pokud pole s name="topic_source" má hodnotu "custom".
 *
 * Použití:
 *   VisibilityController.init();             // autoInit nad celým formulářem
 *   VisibilityController.init(formElement);   // nad konkrétním formulářem
 */

import { MODULE } from 'assets/ts/Const';

/** Jedna podmínka viditelnosti: název pole -> očekávaná hodnota */
interface VisibilityCondition {
	/** Název zdrojového pole (atribut name) */
	fieldName: string;
	/** Očekávaná hodnota pro zobrazení */
	expectedValue: string | boolean;
}

/** Interní binding – blok + jeho podmínky */
interface VisibilityBinding {
	/** Wrapper element bloku (.pb-module__block) */
	block: HTMLElement;
	/** Všechny podmínky, které musí být splněny pro zobrazení */
	conditions: VisibilityCondition[];
}

export class VisibilityController {

	/** Selektor pro bloky s podmíněnou viditelností */
	private static readonly ATTR = 'data-visible-if';

	/** CSS třída přidaná na skrytý blok (umožňuje animace) */
	private static readonly HIDDEN_CLASS = MODULE.BLOCK_HIDDEN;

	/** Všechny aktivní bindingy */
	private bindings: VisibilityBinding[] = [];

	/** Formulář, ke kterému je controller vázán */
	private form: HTMLFormElement;

	/** Reference na event handler pro pozdější unbind */
	private changeHandler: (e: Event) => void;

	constructor(form: HTMLFormElement) {
		this.form = form;
		this.changeHandler = this.onFieldChange.bind(this);
		this.discover();
		this.bindEvents();
		this.evaluateAll();
	}

	// ─── Statické API ──────────────────────────────────────────────

	/**
	 * Inicializuje VisibilityController pro všechny formuláře (nebo konkrétní).
	 *
	 * @param scope Volitelný kořenový element (výchozí: document)
	 * @returns Pole instancí controlleru
	 */
	static init(scope?: HTMLElement | Document): VisibilityController[] {
		const root = scope ?? document;
		const forms = root.querySelectorAll<HTMLFormElement>('form');
		const controllers: VisibilityController[] = [];

		forms.forEach(form => {
			// Inicializuj pouze pokud formulář obsahuje bloky s visible_if
			if (form.querySelector(`[${VisibilityController.ATTR}]`)) {
				controllers.push(new VisibilityController(form));
			}
		});

		return controllers;
	}

	// ─── Discovery ─────────────────────────────────────────────────

	/**
	 * Najde všechny bloky s data-visible-if a vytvoří bindingy.
	 */
	private discover(): void {
		const blocks = this.form.querySelectorAll<HTMLElement>(`[${VisibilityController.ATTR}]`);

		blocks.forEach(block => {
			const raw = block.getAttribute(VisibilityController.ATTR);
			if (!raw) return;

			try {
				const parsed = JSON.parse(raw) as Record<string, string | boolean>;
				const conditions: VisibilityCondition[] = Object.entries(parsed).map(
					([fieldName, expectedValue]) => ({ fieldName, expectedValue })
				);

				if (conditions.length > 0) {
					this.bindings.push({ block, conditions });
				}
			} catch (e) {
				console.warn('[VisibilityController] Neplatný JSON v data-visible-if:', raw, e);
			}
		});
	}

	// ─── Event binding ─────────────────────────────────────────────

	/**
	 * Naváže change event listener na formulář (event delegation).
	 *
	 * Reaguje na:
	 * - radio: change event
	 * - checkbox: change event
	 */
	private bindEvents(): void {
		this.form.addEventListener('change', this.changeHandler);
	}

	/**
	 * Handler pro change event – vyhodnotí podmínky při změně pole.
	 */
	private onFieldChange(e: Event): void {
		const target = e.target as HTMLInputElement;

		if (!(target instanceof HTMLInputElement)) return;

		// Reagujeme pouze na radio a checkbox
		if (target.type !== 'radio' && target.type !== 'checkbox') return;

		// Vyhodnotíme pouze bindingy, které odkazují na změněné pole
		const fieldName = target.name;

		this.bindings.forEach(binding => {
			const isRelevant = binding.conditions.some(c => c.fieldName === fieldName);
			if (isRelevant) {
				this.evaluate(binding);
			}
		});
	}

	// ─── Evaluace ──────────────────────────────────────────────────

	/**
	 * Vyhodnotí všechny bindingy (voláno při inicializaci).
	 */
	private evaluateAll(): void {
		this.bindings.forEach(binding => this.evaluate(binding));
	}

	/**
	 * Vyhodnotí jeden binding – zobrazí nebo skryje blok.
	 *
	 * Všechny podmínky musí být splněny (AND logika).
	 */
	private evaluate(binding: VisibilityBinding): void {
		const allMet = binding.conditions.every(condition =>
			this.checkCondition(condition)
		);

		this.toggleBlock(binding.block, allMet);
	}

	/**
	 * Zkontroluje jednu podmínku proti aktuálnímu stavu formuláře.
	 */
	private checkCondition(condition: VisibilityCondition): boolean {
		const { fieldName, expectedValue } = condition;

		// Najdeme zdrojové pole ve formuláři
		const fields = this.form.querySelectorAll<HTMLInputElement>(`[name="${fieldName}"]`);

		if (fields.length === 0) {
			console.warn(`[VisibilityController] Pole "${fieldName}" nenalezeno ve formuláři.`);
			return false;
		}

		const firstField = fields[0];

		// Checkbox – porovnáváme stav checked
		if (firstField.type === 'checkbox') {
			return this.checkCheckbox(firstField, expectedValue);
		}

		// Radio – hledáme vybranou hodnotu ve skupině
		if (firstField.type === 'radio') {
			return this.checkRadioGroup(fields, expectedValue);
		}

		// Fallback pro ostatní typy (select, text) – porovnáváme value
		return firstField.value === String(expectedValue);
	}

	/**
	 * Vyhodnotí podmínku pro checkbox.
	 *
	 * Podporuje:
	 * - boolean: true/false → checked/unchecked
	 * - string: porovná s value checkboxu (pokud je checked)
	 */
	private checkCheckbox(field: HTMLInputElement, expected: string | boolean): boolean {
		if (typeof expected === 'boolean') {
			return field.checked === expected;
		}

		// String hodnota – checkbox musí být checked A mít danou value
		return field.checked && field.value === expected;
	}

	/**
	 * Vyhodnotí podmínku pro radio group.
	 *
	 * Hledá aktuálně vybraný radio a porovná jeho value s očekávanou hodnotou.
	 */
	private checkRadioGroup(fields: NodeListOf<HTMLInputElement>, expected: string | boolean): boolean {
		const checked = Array.from(fields).find(r => r.checked);

		if (!checked) return false;

		return checked.value === String(expected);
	}

	// ─── DOM manipulace ────────────────────────────────────────────

	/**
	 * Zobrazí nebo skryje blok.
	 *
	 * Používá CSS třídu a aria-hidden pro přístupnost.
	 * Při skrytí nastaví disabled na všechny formulářové prvky uvnitř bloku,
	 * aby se neodesílaly při submit.
	 */
	private toggleBlock(block: HTMLElement, visible: boolean): void {
		block.classList.toggle(VisibilityController.HIDDEN_CLASS, !visible);
		block.setAttribute('aria-hidden', String(!visible));

		// Disable/enable formulářové prvky uvnitř skrytého bloku
		const formElements = block.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
			'input, select, textarea'
		);

		formElements.forEach(el => {
			if (!visible) {
				// Uložíme původní disabled stav
				if (!el.hasAttribute('data-visibility-disabled')) {
					el.setAttribute('data-visibility-disabled', el.disabled ? 'true' : 'false');
				}
				el.disabled = true;
			} else {
				// Obnovíme původní stav
				const wasDisabled = el.getAttribute('data-visibility-disabled');
				if (wasDisabled !== null) {
					el.disabled = wasDisabled === 'true';
					el.removeAttribute('data-visibility-disabled');
				}
			}
		});
	}

	// ─── Cleanup ───────────────────────────────────────────────────

	/**
	 * Zruší všechny bindingy a event listenery.
	 */
	destroy(): void {
		this.form.removeEventListener('change', this.changeHandler);
		this.bindings = [];
	}
}
