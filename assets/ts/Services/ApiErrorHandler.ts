/**
 * ApiErrorHandler – Klientská vrstva pro zpracování chyb z PHP API.
 *
 * PHP ApiHandler vrací minimální JSON payload se surovými daty výjimky.
 * Tento handler přebírá veškerou zodpovědnost za:
 *   - Kategorizaci chyby (security, validation, timeout, network…)
 *   - Mapování PHP kódů (AiException 1001–1006) na uživatelské hlášky
 *   - Rozlišení HTTP chyb vs. API chyb vs. síťových chyb
 *   - Zpracování neočekávaných odpovědí (HTML fatal errors)
 *   - Zobrazení notifikací přes ErrorHandler
 *   - Emitování strukturovaných chybových eventů na EventBus
 *
 * Použití:
 *   const apiErrors = new ApiErrorHandler(events, errorHandler);
 *   // V transport/catch bloku:
 *   apiErrors.handleTransportError(error);
 *   // Po fetch response:
 *   apiErrors.handleResponse(response, body);
 *
 * @see ErrorHandler – Nízkoúrovňový renderer a normalizátor chyb
 * @see HttpTransport – Transport, který deleguje chyby sem
 */

import { EventBus } from 'assets/ts/Core/EventBus';
import { ErrorHandler } from 'assets/ts/Core/ErrorHandler';
import {
	type AiBridgeError,
	type ServerResponse,
	type ApiError,
	ErrorCode,
} from 'assets/ts/Types';

// ─── Types ────────────────────────────────────────────

/**
 * PHP error type identifikátory vrácené z ApiHandler::resolveErrorType().
 */
type PhpErrorType = 'security' | 'invalid_json' | 'ai_provider' | 'internal_error';

/**
 * Rozšířené options pro ApiErrorHandler.
 */
export interface ApiErrorHandlerOptions {
	/** Zobrazovat verbose detaily v notifikacích (default: false) */
	verbose?: boolean;
	/**
	 * Debug mód – zobrazí raw PHP chybové zprávy (AiException message)
	 * místo generických uživatelských hlášek. Automaticky zapne zobrazení
	 * detail bloku s kontextem chyby. (default: false)
	 */
	debug?: boolean;
	/** Vlastní mapování PHP error typů na uživatelské hlášky */
	typeMessages?: Partial<Record<PhpErrorType, string>>;
	/** Vlastní mapování AiException kódů na uživatelské hlášky */
	codeMessages?: Partial<Record<number, string>>;
}

// ─── Konstanty ────────────────────────────────────────

/** Výchozí uživatelské hlášky podle PHP error type */
const PHP_TYPE_MESSAGES: Record<PhpErrorType, string> = {
	security:       'Přístup zamítnut — neplatný bezpečnostní podpis.',
	invalid_json:   'Neplatný formát požadavku.',
	ai_provider:    'Chyba AI poskytovatele.',
	internal_error: 'Interní chyba serveru.',
};

/** Mapování PHP error type → AiBridgeError type */
const PHP_TYPE_MAP: Record<PhpErrorType, AiBridgeError['type']> = {
	security:       'http',
	invalid_json:   'validation',
	ai_provider:    'api',
	internal_error: 'unknown',
};

/** Uživatelské hlášky podle AiException kódů (1001–1006) a SecurityException kódů (2001–2003) */
const AI_CODE_MESSAGES: Record<number, string> = {
	[ErrorCode.INVALID_REQUEST]:  'Požadavek obsahuje neplatná data.',
	[ErrorCode.VALIDATION]:       'Vstupní data neprošla validací.',
	[ErrorCode.CONNECTION]:       'Nelze se připojit k AI poskytovateli.',
	[ErrorCode.TIMEOUT]:          'Požadavek na AI poskytovatele vypršel.',
	[ErrorCode.INVALID_RESPONSE]: 'AI poskytovatel vrátil neplatnou odpověď.',
	[ErrorCode.API]:              'AI poskytovatel vrátil chybu.',

	// SecurityException kódy
	[ErrorCode.INVALID_SIGNATURE]: 'Neplatný bezpečnostní podpis požadavku.',
	[ErrorCode.EXPIRED_TOKEN]:     'Platnost bezpečnostního tokenu vypršela. Obnovte stránku.',
	[ErrorCode.MISSING_TOKEN]:     'Požadavek neobsahuje bezpečnostní token.',

	// JsonException kódy
	[ErrorCode.INVALID_JSON]:      'Neplatný formát JSON.',
	[ErrorCode.JSON_DEPTH]:        'Překročena maximální hloubka JSON.',
	[ErrorCode.JSON_UTF8]:         'Neplatné UTF-8 znaky.',
};

/** Mapování AiException / SecurityException kódu → AiBridgeError type */
const AI_CODE_TYPE_MAP: Record<number, AiBridgeError['type']> = {
	[ErrorCode.INVALID_REQUEST]:  'validation',
	[ErrorCode.VALIDATION]:       'validation',
	[ErrorCode.CONNECTION]:       'network',
	[ErrorCode.TIMEOUT]:          'timeout',
	[ErrorCode.INVALID_RESPONSE]: 'parse',
	[ErrorCode.API]:              'api',

	// SecurityException → http (403/401)
	[ErrorCode.INVALID_SIGNATURE]: 'http',
	[ErrorCode.EXPIRED_TOKEN]:     'http',
	[ErrorCode.MISSING_TOKEN]:     'http',

	// JsonException → parse
	[ErrorCode.INVALID_JSON]:      'parse',
	[ErrorCode.JSON_DEPTH]:        'parse',
	[ErrorCode.JSON_UTF8]:         'parse',
};

/** Výchozí HTTP status → hláška (fallback pokud PHP nevrátil api.error) */
const HTTP_STATUS_MESSAGES: Record<number, string> = {
	400: 'Neplatný požadavek.',
	403: 'Přístup zamítnut.',
	404: 'API endpoint nenalezen.',
	422: 'Chyba validace vstupních dat.',
	429: 'Příliš mnoho požadavků — zkuste to později.',
	500: 'Interní chyba serveru.',
	502: 'Server AI poskytovatele je dočasně nedostupný.',
	503: 'Služba je dočasně nedostupná.',
	504: 'Požadavek na AI poskytovatele vypršel.',
};

const DEFAULTS: Required<Omit<ApiErrorHandlerOptions, 'typeMessages' | 'codeMessages'>> = {
	verbose: false,
	debug: false,
};

// ─── ApiErrorHandler ──────────────────────────────────

export class ApiErrorHandler {
	private readonly events: EventBus;
	private readonly errorHandler: ErrorHandler;
	private readonly options: typeof DEFAULTS;
	private readonly typeMessages: Record<PhpErrorType, string>;
	private readonly codeMessages: Record<number, string>;

	constructor(
		events: EventBus,
		errorHandler: ErrorHandler,
		options: ApiErrorHandlerOptions = {},
	) {
		this.events = events;
		this.errorHandler = errorHandler;

		const { typeMessages, codeMessages, ...rest } = options;
		this.options = { ...DEFAULTS, ...rest };
		this.typeMessages = { ...PHP_TYPE_MESSAGES, ...typeMessages };
		this.codeMessages = { ...AI_CODE_MESSAGES, ...codeMessages };
	}

	// ─── Hlavní entry points ──────────────────────────

	/**
	 * Zpracuje fetch Response + rozparsované body.
	 * Vrátí null pokud odpověď je úspěšná, jinak AiBridgeError.
	 *
	 * Volá se z HttpTransport po obdržení odpovědi:
	 * ```ts
	 * const error = apiErrors.handleResponse(response, body);
	 * if (error) return null; // transport vrátí null = selhání
	 * ```
	 */
	handleResponse(response: Response, body?: ServerResponse | null): AiBridgeError | null {
		// Úspěšná odpověď bez API chyby
		if (response.ok && (!body?.api || body.api.success)) {
			return null;
		}

		const apiError = body?.api?.error;

		// HTTP chyba s nebo bez API error detailu
		if (!response.ok) {
			return this.processHttpError(response, apiError);
		}

		// HTTP 200 ale api.success === false (PHP vrátil chybu)
		if (apiError) {
			return this.processApiError(apiError);
		}

		return null;
	}

	/**
	 * Zpracuje neočekávanou odpověď (HTML místo JSON, prázdné tělo).
	 * Pokrývá případ PHP fatal error → HTML output.
	 */
	handleUnexpectedResponse(response: Response, rawBody?: string): AiBridgeError {
		const showDetail = this.options.verbose || this.options.debug;

		const error: AiBridgeError = {
			type: 'parse',
			code: ErrorCode.INVALID_RESPONSE,
			message: 'Server vrátil neočekávanou odpověď místo JSON.',
			source: 'api',
			detail: showDetail ? {
				status: response.status,
				contentType: response.headers.get('content-type'),
				bodyPreview: rawBody?.substring(0, 500),
			} : undefined,
		};

		return this.emit(error);
	}

	/**
	 * Zpracuje síťovou/transport chybu (TypeError, AbortError, generická Error).
	 * Volá se z catch bloku v HttpTransport.
	 */
	handleTransportError(error: unknown): AiBridgeError {
		// AbortError → timeout
		if (error instanceof DOMException && error.name === 'AbortError') {
			return this.emit({
				type: 'timeout',
				code: ErrorCode.TIMEOUT,
				message: 'Požadavek byl zrušen nebo vypršel timeout.',
				source: 'transport',
			});
		}

		// TypeError (Failed to fetch) → network
		if (error instanceof TypeError) {
			return this.emit({
				type: 'network',
				code: ErrorCode.CONNECTION,
				message: 'Nelze se připojit k serveru. Zkontrolujte připojení k internetu.',
				source: 'transport',
				detail: (this.options.verbose || this.options.debug) ? error.message : undefined,
			});
		}

		// Generická chyba → delegace na ErrorHandler
		return this.errorHandler.handle(error);
	}

	// ─── Interní zpracování ───────────────────────────

	/**
	 * Zpracuje HTTP chybu (4xx/5xx) s volitelným API error detailem z PHP.
	 */
	private processHttpError(response: Response, apiError?: ApiError): AiBridgeError {
		// Pokud PHP vrátil strukturovanou chybu → použij ji
		if (apiError) {
			return this.processApiError(apiError, response.status);
		}

		// Fallback na HTTP status message
		const message = HTTP_STATUS_MESSAGES[response.status]
			?? `Neočekávaná chyba serveru (${response.status}).`;

		const showDetail = this.options.verbose || this.options.debug;

		return this.emit({
			type: 'http',
			code: response.status,
			message,
			source: 'api',
			detail: showDetail ? {
				status: response.status,
				statusText: response.statusText,
			} : undefined,
		});
	}

	/**
	 * Zpracuje API error objekt z PHP odpovědi.
	 *
	 * Rozpoznává:
	 * 1. AiException / JsonException kódy (1001–1006, 3001–3003) → specifické hlášky a typy
	 * 2. PHP error type (security, invalid_json…) → mapované hlášky
	 * 3. Fallback na raw message z PHP
	 *
	 * `message` je vždy klientsky přívětivá mapovaná zpráva.
	 * `serverMessage` je vždy originální PHP zpráva (dostupná pro debug zobrazení).
	 */
	private processApiError(apiError: ApiError, httpStatus?: number): AiBridgeError {
		const code = apiError.code;
		const phpType = apiError.type as PhpErrorType;
		const serverMessage = apiError.message || undefined;
		const showDetail = this.options.verbose || this.options.debug;

		// 1. Pokus o mapování přes kód výjimky (AiException 1001–1006 / JsonException 3001–3003)
		if (code && this.codeMessages[code]) {
			return this.emit({
				type: AI_CODE_TYPE_MAP[code] ?? 'api',
				code,
				message: this.codeMessages[code],
				serverMessage,
				source: 'api',
				detail: showDetail ? apiError : undefined,
			});
		}

		// 2. Pokus o mapování přes PHP error type
		if (phpType && this.typeMessages[phpType]) {
			return this.emit({
				type: PHP_TYPE_MAP[phpType] ?? 'unknown',
				code: code ?? httpStatus,
				message: this.typeMessages[phpType],
				serverMessage,
				source: 'api',
				detail: showDetail ? apiError : undefined,
			});
		}

		// 3. Fallback – použij raw message z PHP
		return this.emit({
			type: 'api',
			code: code ?? httpStatus,
			message: serverMessage || 'API vrátila nespecifikovanou chybu.',
			serverMessage,
			source: 'api',
			detail: showDetail ? apiError : undefined,
		});
	}

	/**
	 * Emituje chybu přes ErrorHandler (EventBus + notifikace) a vrátí ji.
	 */
	private emit(error: AiBridgeError): AiBridgeError {
		return this.errorHandler.handle(error);
	}
}
