/**
 * ErrorHandler - Centrální zpracování chyb
 *
 * Zpracovává:
 * - HTTP chyby (4xx, 5xx)
 * - Síťové chyby (timeout, connection refused)
 * - Chyby parsování JSON
 * - API chyby z backendu (api.error)
 * - Zobrazení error hlášky uživateli
 */

import { EventBus } from './EventBus';
import { type AiBridgeError, type ServerResponse, type ApiError } from 'assets/ts/Types';
import { Dom } from "./Dom";

export interface ErrorHandlerOptions {
	/** Selektor kontejneru pro error hlášky */
	errorContainerSelector?: string;
	/** CSS třída pro error hlášku */
	errorAlertClass?: string;
	/** Automaticky schovat error po X ms (0 = neschovat) */
	autoHideDelay?: number;
	/** Zobrazovat detailní chyby (pro vývoj) */
	verbose?: boolean;
	/** Vlastní texty pro HTTP kódy */
	httpMessages?: Record<number, string>;
}

const DEFAULTS: Required<ErrorHandlerOptions> = {
	errorContainerSelector: '.ai-module',
	errorAlertClass: 'ai-error-alert',
	autoHideDelay: 10000,
	verbose: false,
	httpMessages: {
		400: 'Neplatný požadavek.',
		403: 'Přístup zamítnut — neplatný bezpečnostní podpis.',
		404: 'API endpoint nenalezen.',
		422: 'Chyba validace vstupních dat.',
		429: 'Příliš mnoho požadavků. Zkuste to později.',
		500: 'Interní chyba serveru.',
		502: 'Server dočasně nedostupný.',
		503: 'Služba je dočasně nedostupná.',
		504: 'Požadavek vypršel — server neodpověděl včas.',
	},
};

export class ErrorHandler {
	private readonly options: Required<ErrorHandlerOptions>;
	private readonly events: EventBus;

	constructor(events: EventBus, options: ErrorHandlerOptions = {}) {
		this.events = events;
		this.options = {
			...DEFAULTS,
			httpMessages: { ...DEFAULTS.httpMessages, ...options.httpMessages },
			...options,
		};
	}

	// ─── Zpracování různých typů chyb ──────────────────────────────

	/**
	 * Zpracuje síťovou chybu (fetch selhal úplně)
	 */
	handleNetworkError(error: Error): AiBridgeError {
		const bridgeError: AiBridgeError = {
			type: 'network',
			message: 'Nelze se připojit k serveru. Zkontrolujte připojení k internetu.',
			detail: error.message,
		};

		this.show(bridgeError);
		this.events.emit('error', { error: bridgeError });
		return bridgeError;
	}

	/**
	 * Zpracuje HTTP chybu (response.ok === false)
	 */
	handleHttpError(response: Response, body?: ServerResponse | null): AiBridgeError {
		const apiError = body?.api?.error;
		const message = apiError?.message
			|| this.options.httpMessages[response.status]
			|| `Chyba serveru (${response.status})`;

		const bridgeError: AiBridgeError = {
			type: 'http',
			message,
			detail: this.options.verbose ? {
				status: response.status,
				statusText: response.statusText,
				apiError,
			} : undefined,
		};

		this.show(bridgeError);
		this.events.emit('error', { error: bridgeError, statusCode: response.status });
		return bridgeError;
	}

	/**
	 * Zpracuje chybu parsování JSON
	 */
	handleParseError(error: Error): AiBridgeError {
		const bridgeError: AiBridgeError = {
			type: 'parse',
			message: 'Server vrátil neočekávanou odpověď.',
			detail: this.options.verbose ? error.message : undefined,
		};

		this.show(bridgeError);
		this.events.emit('error', { error: bridgeError });
		return bridgeError;
	}

	/**
	 * Zpracuje API chybu z backendu (api.success === false, ale HTTP 200)
	 */
	handleApiError(apiError: ApiError): AiBridgeError {
		const bridgeError: AiBridgeError = {
			type: 'http',
			message: apiError.message || 'API vrátila chybu.',
			detail: this.options.verbose ? apiError : undefined,
		};

		this.show(bridgeError);
		this.events.emit('error', { error: bridgeError, statusCode: apiError.code });
		return bridgeError;
	}

	// ─── UI zobrazení ──────────────────────────────────────────────

	/**
	 * Zobrazí error hlášku uživateli
	 */
	show(error: AiBridgeError): void {
		this.dismiss(); // odstraň předchozí

		const container = Dom.q(this.options.errorContainerSelector);

		if (!container) {
			console.error('[ErrorHandler] Kontejner nenalezen:', this.options.errorContainerSelector);
			return;
		}

		const alert = Dom.create('div', {
			className: this.options.errorAlertClass,
			attr: { role: 'alert' },
		});

		alert.html(`
			<div class="${this.options.errorAlertClass}__content">
				<strong class="${this.options.errorAlertClass}__title">Chyba</strong>
				<p class="${this.options.errorAlertClass}__message">${this.escapeHtml(error.message)}</p>
				${this.options.verbose && error.detail ? `<pre class="${this.options.errorAlertClass}__detail">${this.escapeHtml(JSON.stringify(error.detail, null, 2))}</pre>` : ''}
			</div>
			<button class="${this.options.errorAlertClass}__close" type="button" aria-label="Zavřít">&times;</button>
		`)

		// Close button
		const closeBtn = alert.find(`.${this.options.errorAlertClass}__close`);
		closeBtn?.on('click', () => this.dismiss());

		Dom.wrap(container).prepend(alert);

		// Auto-hide
		if (this.options.autoHideDelay > 0) {
			setTimeout(() => this.dismiss(), this.options.autoHideDelay);
		}
	}

	/**
	 * Odstraní zobrazenou error hlášku
	 */
	dismiss(): void {
		Dom.wrapAll(Dom.qa(`.${this.options.errorAlertClass}`)).each((el, __) => el.remove());
	}

	// ─── Helpers ───────────────────────────────────────────────────

	private escapeHtml(str: string): string {
		return Dom.create('div').text(str).html();
	}
}
