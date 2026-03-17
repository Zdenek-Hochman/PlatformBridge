/**
 * LoadingManager - Správa loading stavů
 *
 * Umožňuje:
 * - Zobrazení/skrytí loaderu na tlačítku nebo v kontejneru
 * - Disable formulářových prvků během načítání
 * - Emitování loading eventů
 */

import { EventBus } from '../Core/EventBus';
import { MODULE } from 'assets/ts/Const';

export interface LoadingOptions {
	/** CSS třída pro loading stav na wrapperu */
	loadingClass?: string;
	/** CSS třída pro loading stav na tlačítku */
	buttonLoadingClass?: string;
	/** Text tlačítka během loadingu (null = neměnit) */
	buttonLoadingText?: string | null;
	/** Zobrazit overlay nad formulářem */
	showOverlay?: boolean;
	/** CSS třída overlayu */
	overlayClass?: string;
	/** Disable formuláře během loadingu */
	disableForm?: boolean;
}

const DEFAULTS: Required<LoadingOptions> = {
	loadingClass: MODULE.LOADING,
	buttonLoadingClass: MODULE.SUBMIT_LOADING,
	buttonLoadingText: 'Generuji...',
	showOverlay: true,
	overlayClass: MODULE.OVERLAY,
	disableForm: true,
};

export class LoadingManager {
	private readonly options: Required<LoadingOptions>;
	private readonly events: EventBus;

	private isLoading = false;
	private originalButtonText: string = '';
	private overlayElement: HTMLElement | null = null;

	constructor(events: EventBus, options: LoadingOptions = {}) {
		this.events = events;
		this.options = { ...DEFAULTS, ...options };
	}

	// ─── Start / Stop ──────────────────────────────────────────────

	/**
	 * Spustí loading stav
	 */
	start(form: HTMLFormElement, button?: HTMLElement | null, message?: string): void {
		if (this.isLoading) return;
		this.isLoading = true;

		// Loading třída na formulář
		form.classList.add(this.options.loadingClass);

		// Tlačítko
		if (button) {
			button.classList.add(this.options.buttonLoadingClass);
			this.originalButtonText = button.innerHTML || '';

			if (this.options.buttonLoadingText) {
				button.innerHTML = this.options.buttonLoadingText;
			}
		}

		// Overlay
		if (this.options.showOverlay) {
			this.createOverlay(form);
		}

		// Disable
		if (this.options.disableForm) {
			this.setFormDisabled(form, true);
		}

		this.events.emit('loading', { state: true, message });
	}

	/**
	 * Zastaví loading stav
	 */
	stop(form: HTMLFormElement, button?: HTMLElement | null): void {
		if (!this.isLoading) return;
		this.isLoading = false;

		// Odstraň loading třídu
		form.classList.remove(this.options.loadingClass);

		// Obnov tlačítko
		if (button) {
			button.classList.remove(this.options.buttonLoadingClass);
			button.innerHTML = this.originalButtonText;
		}

		// Odstraň overlay
		this.removeOverlay();

		// Enable formulář
		if (this.options.disableForm) {
			this.setFormDisabled(form, false);
		}

		this.events.emit('loading', { state: false });
	}

	/**
	 * Vrátí, zda probíhá loading
	 */
	getIsLoading(): boolean {
		return this.isLoading;
	}

	// ─── Privátní metody ───────────────────────────────────────────

	private createOverlay(container: HTMLElement): void {
		this.removeOverlay(); // zabránit duplicitám

		this.overlayElement = document.createElement('div');
		this.overlayElement.className = this.options.overlayClass;
		this.overlayElement.innerHTML = `
			<div class="${this.options.overlayClass}-spinner"></div>
			<span class="${this.options.overlayClass}-text">Generuji obsah...</span>
		`;

		container.style.position = 'relative';
		container.appendChild(this.overlayElement);
	}

	private removeOverlay(): void {
		if (this.overlayElement) {
			this.overlayElement.remove();
			this.overlayElement = null;
		}
	}

	private setFormDisabled(form: HTMLFormElement, disabled: boolean): void {
		const elements = Array.from(form.elements) as HTMLElement[];
		for (const el of elements) {
			if (el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement || el instanceof HTMLButtonElement) {
				if (disabled) {
					el.dataset.wasDisabled = el.disabled ? 'true' : 'false';
					el.disabled = true;
				} else {
					// Obnov původní stav
					el.disabled = el.dataset.wasDisabled === 'true';
					delete el.dataset.wasDisabled;
				}
			}
		}
	}
}
