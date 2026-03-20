/**
 * ErrorHandler – Centrální zpracování, normalizace a zobrazení chyb
 *
 * Zpracovává:
 * - HTTP chyby (4xx, 5xx) s mapováním na srozumitelné hlášky
 * - Síťové chyby (timeout, connection refused)
 * - Chyby parsování JSON / neočekávané odpovědi (PHP fatal → HTML)
 * - API chyby z backendu (api.error) s kódy z AiException
 * - DOM eventy a vlastní zdroje chyb (rozšiřitelné přes normalizers)
 * - Obecné notifikace (success, info, warning)
 *
 * Architektura:
 * - Normalizuje libovolnou chybu do {@link AiBridgeError}
 * - Mapuje kódy z PHP {@link AiException} (1001–1006) na titulky
 * - Deleguje vykreslení na {@link MessageRenderer}
 * - Naslouchá EventBus 'error' eventům (z transportů)
 * - Rozšiřitelný přes vlastní {@link ErrorNormalizer} funkce
 */

import { EventBus } from './EventBus';
import { MessageRenderer, type MessageRendererOptions } from './MessageRenderer';
import {
	type AiBridgeError,
	type ServerResponse,
	type ApiError,
	type MessageLevel,
	ErrorCode,
} from 'assets/ts/Types';

// ─── Types ────────────────────────────────────────────

/**
 * Vlastní normalizátor chyb.
 * Vrací AiBridgeError pokud umí chybu zpracovat, jinak null.
 *
 * @example
 * // Normalizátor pro DOM security eventy
 * const securityNormalizer: ErrorNormalizer = (error) => {
 *     if (error instanceof SecurityPolicyViolationEvent) {
 *         return { type: 'dom', message: `CSP: ${error.violatedDirective}`, source: 'dom' };
 *     }
 *     return null;
 * };
 */
export type ErrorNormalizer = (error: unknown) => AiBridgeError | null;

export interface ErrorHandlerOptions {
	/** Auto-subscribe na EventBus 'error' eventy z transportů (default: true) */
	autoListen?: boolean;
	/** Zobrazovat detailní chyby v notifikacích (default: false) */
	verbose?: boolean;
	/**
	 * Debug mód – zobrazuje raw PHP chybové zprávy (serverMessage)
	 * a automaticky zapne detail blok v notifikacích. (default: false)
	 */
	debug?: boolean;
	/** Vlastní texty pro HTTP status kódy */
	httpMessages?: Record<number, string>;
	/** Auto-hide delay pro error notifikace v ms (default: 10000) */
	autoHideDelay?: number;
	/** Přetížení nastavení MessageRendereru */
	rendererOptions?: MessageRendererOptions;
}

// ─── Konstanty ────────────────────────────────────────

/** Výchozí HTTP status → hláška */
const HTTP_MESSAGES: Record<number, string> = {
	400: 'Neplatný požadavek.',
	401: 'Neautorizovaný přístup.',
	403: 'Přístup zamítnut — neplatný bezpečnostní podpis.',
	404: 'API endpoint nenalezen.',
	405: 'Nepodporovaná HTTP metoda.',
	408: 'Požadavek vypršel.',
	422: 'Chyba validace vstupních dat.',
	429: 'Příliš mnoho požadavků. Zkuste to později.',
	500: 'Interní chyba serveru.',
	502: 'Server dočasně nedostupný.',
	503: 'Služba je dočasně nedostupná.',
	504: 'Požadavek vypršel — server neodpověděl včas.',
};

/** Titulky podle ErrorCode z AiException (PHP) a SecurityException */
const CODE_TITLES: Record<number, string> = {
	[ErrorCode.INVALID_REQUEST]:  'Neplatný požadavek',
	[ErrorCode.VALIDATION]:       'Chyba validace',
	[ErrorCode.CONNECTION]:       'Chyba připojení',
	[ErrorCode.TIMEOUT]:          'Časový limit vypršel',
	[ErrorCode.INVALID_RESPONSE]: 'Neplatná odpověď',
	[ErrorCode.API]:              'Chyba API',

	// SecurityException titulky
	[ErrorCode.INVALID_SIGNATURE]: 'Bezpečnostní chyba',
	[ErrorCode.EXPIRED_TOKEN]:     'Token vypršel',
	[ErrorCode.MISSING_TOKEN]:     'Chybějící token',

	// JsonException titulky
	[ErrorCode.INVALID_JSON]:      'Neplatné výstupní data',
	[ErrorCode.JSON_DEPTH]:        'Neplatné výstupní data',
	[ErrorCode.JSON_UTF8]:         'Neplatné výstupní data',
};

/**
 * Uživatelsky přívětivé hlášky pro ErrorCode z AiException / SecurityException / JsonException.
 * Čistý text bez interních PHP detailů – zobrazuje se jako tělo notifikace.
 * Raw PHP message z throw se ukládá do serverMessage a zobrazuje jen v debug módu.
 */
const CODE_USER_MESSAGES: Record<number, string> = {
	// AiException (1001–1006)
	[ErrorCode.INVALID_REQUEST]:  'Požadavek obsahuje neplatná data.',
	[ErrorCode.VALIDATION]:       'Vstupní data neprošla validací.',
	[ErrorCode.CONNECTION]:       'Nelze se připojit k AI poskytovateli.',
	[ErrorCode.TIMEOUT]:          'Požadavek na AI poskytovatele vypršel.',
	[ErrorCode.INVALID_RESPONSE]: 'AI poskytovatel vrátil neplatnou odpověď.',
	[ErrorCode.API]:              'AI poskytovatel vrátil chybu.',

	// SecurityException (2001–2003)
	[ErrorCode.INVALID_SIGNATURE]: 'Neplatný bezpečnostní podpis požadavku.',
	[ErrorCode.EXPIRED_TOKEN]:     'Platnost bezpečnostního tokenu vypršela. Obnovte stránku.',
	[ErrorCode.MISSING_TOKEN]:     'Požadavek neobsahuje bezpečnostní token.',

	// JsonException (3001–3003)
	[ErrorCode.JSON_DEPTH]:   'Překročena maximální hloubka JSON.',
	[ErrorCode.INVALID_JSON]: 'Neplatný formát JSON.',
	[ErrorCode.JSON_UTF8]:    'Neplatné UTF-8 znaky ve výstupu.',
};

/** Mapování ErrorCode → AiBridgeError type */
const CODE_TYPE_MAP: Record<number, AiBridgeError['type']> = {
	[ErrorCode.INVALID_REQUEST]:  'validation',
	[ErrorCode.VALIDATION]:       'validation',
	[ErrorCode.CONNECTION]:       'network',
	[ErrorCode.TIMEOUT]:          'timeout',
	[ErrorCode.INVALID_RESPONSE]: 'parse',
	[ErrorCode.API]:              'api',
	[ErrorCode.INVALID_SIGNATURE]: 'http',
	[ErrorCode.EXPIRED_TOKEN]:     'http',
	[ErrorCode.MISSING_TOKEN]:     'http',
	[ErrorCode.INVALID_JSON]:      'parse',
	[ErrorCode.JSON_DEPTH]:        'parse',
	[ErrorCode.JSON_UTF8]:         'parse',
};

/** Titulky podle error type (fallback) */
const TYPE_TITLES: Record<AiBridgeError['type'], string> = {
	network:    'Chyba sítě',
	http:       'Chyba serveru',
	api:        'Chyba API',
	validation: 'Chyba validace',
	parse:      'Chyba zpracování',
	timeout:    'Časový limit',
	dom:        'Chyba aplikace',
	unknown:    'Neočekávaná chyba',
};

const HANDLER_DEFAULTS: Required<Omit<ErrorHandlerOptions, 'rendererOptions'>> = {
	autoListen: true,
	verbose: false,
	debug: false,
	httpMessages: HTTP_MESSAGES,
	autoHideDelay: 10_000,
};

// ─── ErrorHandler ─────────────────────────────────────

export class ErrorHandler {
	private readonly options: typeof HANDLER_DEFAULTS;
	private readonly events: EventBus;
	private readonly _renderer: MessageRenderer;
	private readonly normalizers: ErrorNormalizer[] = [];

	/** Ochrana proti re-entrancy při emit → subscribe cyklu */
	private handling = false;

	constructor(events: EventBus, options: ErrorHandlerOptions = {}) {
		this.events = events;

		const { rendererOptions, ...rest } = options;
		this.options = {
			...HANDLER_DEFAULTS,
			...rest,
			httpMessages: { ...HANDLER_DEFAULTS.httpMessages, ...rest.httpMessages },
		};

		this._renderer = new MessageRenderer({
			verbose: this.options.verbose,
			debug: this.options.debug,
			autoHideDelay: this.options.autoHideDelay,
			...rendererOptions,
		});

		if (this.options.autoListen) {
			this.listen();
		}
	}

	/** Přímý přístup k rendereru pro pokročilé použití */
	get renderer(): MessageRenderer {
		return this._renderer;
	}

	// ─── Rozšiřitelnost ────────────────────────────────

	/**
	 * Registruje vlastní normalizátor chyb.
	 * Normalizátory se volají v pořadí registrace – první non-null výsledek vyhrává.
	 *
	 * @example
	 * // Rozšíření pro DOM eventy
	 * handler.use((error) => {
	 *     if (error instanceof SecurityPolicyViolationEvent) {
	 *         return { type: 'dom', message: 'Porušení bezpečnostní politiky.', source: 'dom' };
	 *     }
	 *     return null;
	 * });
	 *
	 * // Rozšíření pro vlastní error třídy
	 * handler.use((error) => {
	 *     if (error instanceof MyDomError) {
	 *         return { type: 'dom', code: error.code, message: error.message, source: 'dom' };
	 *     }
	 *     return null;
	 * });
	 */
	use(normalizer: ErrorNormalizer): this {
		this.normalizers.push(normalizer);
		return this;
	}

	// ─── Zpracování chyb ──────────────────────────────

	/**
	 * Univerzální handler – normalizuje libovolnou chybu, emituje event a zobrazí notifikaci.
	 *
	 * Použitelné v catch blocích kdekoliv v aplikaci:
	 * ```ts
	 * try { ... } catch (e) { errorHandler.handle(e); }
	 * ```
	 */
	handle(error: unknown): AiBridgeError {
		return this.process(this.normalize(error));
	}

	/**
	 * Zpracuje HTTP response (s případným parsovaným body).
	 * Vrátí null pokud response je OK a API nevrátila chybu.
	 *
	 * Pokrývá:
	 * - HTTP 4xx/5xx s api.error detailem
	 * - HTTP 200 ale api.success === false
	 */
	handleResponse(response: Response, body?: ServerResponse | null): AiBridgeError | null {
		// Žádná chyba
		if (response.ok && (!body?.api || body.api.success)) return null;

		const showDetail = this.options.verbose || this.options.debug;

		// HTTP chyba (4xx, 5xx)
		if (!response.ok) {
			const apiError = body?.api?.error;
			const code = apiError?.code;
			const serverMessage = apiError?.message || undefined;

			// Priorita: user-friendly mapa podle kódu → HTTP status hláška → server message
			const userMessage = (code && CODE_USER_MESSAGES[code])
				? (this.options.debug && serverMessage ? serverMessage : CODE_USER_MESSAGES[code])
				: (this.options.httpMessages[response.status] ?? `Chyba serveru (${response.status})`);

			return this.process({
				type: (code != null ? CODE_TYPE_MAP[code] : undefined) ?? 'http',
				code: code ?? response.status,
				message: userMessage,
				serverMessage,
				source: 'ajax',
				detail: showDetail ? {
					status: response.status,
					statusText: response.statusText,
					apiError,
				} : undefined,
			});
		}

		// API chyba (HTTP 200 ale api.success === false)
		if (body?.api?.error) {
			return this.handleApiError(body.api.error);
		}

		return null;
	}

	/**
	 * Zpracuje API chybu z backendu (api.error objekt).
	 * Kódy chyb odpovídají PHP AiException (1001–1006), SecurityException (2001–2003)
	 * a JsonException (3001–3003).
	 *
	 * - `message`       → user-friendly hláška z CODE_USER_MESSAGES (nebo fallback)
	 * - `serverMessage` → originální PHP throw message (zobrazí se jen v debug módu)
	 */
	handleApiError(apiError: ApiError): AiBridgeError {
		const showDetail = this.options.verbose || this.options.debug;
		const code = apiError.code;
		const serverMessage = apiError.message || undefined;

		// Preferuj user-friendly text z mapy; v debug módu zobraz PHP message
		const userMessage = (code && CODE_USER_MESSAGES[code])
			? (this.options.debug && serverMessage ? serverMessage : CODE_USER_MESSAGES[code])
			: (serverMessage || 'API vrátila chybu.');

		return this.process({
			type: (code != null ? CODE_TYPE_MAP[code] : undefined) ?? 'api',
			code,
			message: userMessage,
			serverMessage,
			source: 'ajax',
			detail: showDetail ? apiError : undefined,
		});
	}

	/**
	 * Zpracuje neočekávanou odpověď serveru (HTML místo JSON, prázdné tělo atd.).
	 * Pokrývá případ, kdy PHP hodí fatal error a vrátí HTML.
	 */
	handleUnexpectedResponse(response: Response, rawBody?: string): AiBridgeError {
		const showDetail = this.options.verbose || this.options.debug;

		return this.process({
			type: 'parse',
			code: ErrorCode.INVALID_RESPONSE,
			message: 'Server vrátil neočekávanou odpověď.',
			source: 'ajax',
			detail: showDetail ? {
				status: response.status,
				contentType: response.headers.get('content-type'),
				body: rawBody?.substring(0, 500),
			} : undefined,
		});
	}

	// ─── Notifikace (obecné hlášky) ────────────────────

	/**
	 * Zobrazí notifikaci libovolné úrovně.
	 * Pro success, info, warning hlášky které nejsou chyby.
	 *
	 * @example
	 * handler.notify('success', 'Data byla úspěšně uložena.');
	 * handler.notify('info', 'Zpracování probíhá...', 'Průběh');
	 * handler.notify('warning', 'Tato akce je nevratná.');
	 */
	notify(level: MessageLevel, message: string, title?: string): void {
		this._renderer.show({ level, message, title });
		this.events.publish('notification', { level, message, title });
	}

	/**
	 * Odstraní všechny zobrazené notifikace.
	 */
	dismiss(): void {
		this._renderer.clear();
	}

	// ─── EventBus naslouchání ─────────────────────────

	/**
	 * Přihlásí se na EventBus 'error' eventy.
	 * Voláno automaticky pokud autoListen === true.
	 * Zachytává chyby emitované transporty (HttpTransport, SseTransport atd.).
	 */
	private listen(): void {
		this.events.subscribe('error', ({ error, statusCode }) => {
			if (this.handling) return;

			const enriched: AiBridgeError = {
				...error,
				code: error.code ?? statusCode,
			};

			this.render(enriched);
		});
	}

	// ─── Normalizace ──────────────────────────────────

	/**
	 * Normalizuje libovolný typ chyby do AiBridgeError.
	 */
	private normalize(error: unknown): AiBridgeError {
		// Již normalizovaná chyba
		if (this.isAiBridgeError(error)) return error;

		// Vlastní normalizátory (registrované přes use())
		for (const normalizer of this.normalizers) {
			const result = normalizer(error);
			if (result) return result;
		}

		// AbortError → timeout
		if (error instanceof DOMException && error.name === 'AbortError') {
			return {
				type: 'timeout',
				code: ErrorCode.TIMEOUT,
				message: 'Požadavek byl zrušen nebo vypršel timeout.',
			};
		}

		// TypeError (Failed to fetch) → network error
		if (error instanceof TypeError) {
			return {
				type: 'network',
				code: ErrorCode.CONNECTION,
				message: 'Nelze se připojit k serveru. Zkontrolujte připojení k internetu.',
				detail: this.options.verbose ? error.message : undefined,
			};
		}

		// Generická Error instance
		if (error instanceof Error) {
			return {
				type: 'unknown',
				message: error.message || 'Nastala neočekávaná chyba.',
				detail: this.options.verbose ? error.stack : undefined,
			};
		}

		// String message
		if (typeof error === 'string') {
			return { type: 'unknown', message: error };
		}

		// Neznámý typ
		return {
			type: 'unknown',
			message: 'Nastala neočekávaná chyba.',
			detail: this.options.verbose ? error : undefined,
		};
	}

	// ─── Interní zpracování ───────────────────────────

	/**
	 * Emituje error event a zobrazí notifikaci.
	 */
	private process(error: AiBridgeError): AiBridgeError {
		this.emit(error);
		this.render(error);
		return error;
	}

	/**
	 * Emituje 'error' event na EventBus s re-entrancy ochranou.
	 */
	private emit(error: AiBridgeError): void {
		this.handling = true;
		this.events.publish('error', { error, statusCode: error.code });
		this.handling = false;
	}

	/**
	 * Zobrazí error notifikaci přes MessageRenderer.
	 */
	private render(error: AiBridgeError): void {
		this._renderer.show({
			level: 'error',
			title: this.resolveTitle(error),
			message: error.message,
			serverMessage: error.serverMessage,
			detail: error.detail,
		});
	}

	/**
	 * Rozhodne titulek na základě error kódu (AiException) nebo typu.
	 */
	private resolveTitle(error: AiBridgeError): string {
		if (error.code && CODE_TITLES[error.code]) {
			return CODE_TITLES[error.code];
		}
		return TYPE_TITLES[error.type] ?? 'Chyba';
	}

	/**
	 * Type guard pro AiBridgeError.
	 */
	private isAiBridgeError(error: unknown): error is AiBridgeError {
		return (
			typeof error === 'object'
			&& error !== null
			&& 'type' in error
			&& 'message' in error
			&& typeof (error as AiBridgeError).message === 'string'
		);
	}
}
