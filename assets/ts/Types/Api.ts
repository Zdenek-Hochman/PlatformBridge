// ─── Error codes (matching PHP AiException) ──────────

/**
 * Kódy chyb sjednocené s PHP AiException.
 * Umožňují konzistentní mapování mezi serverem a klientem.
 */
export const ErrorCode = {
	INVALID_REQUEST:  1001,
	VALIDATION:       1002,
	CONNECTION:       1003,
	TIMEOUT:          1004,
	INVALID_RESPONSE: 1005,
	API:              1006,

	// SecurityException kódy (2001–2003)
	INVALID_SIGNATURE: 2001,
	EXPIRED_TOKEN:     2002,
	MISSING_TOKEN:     2003,

	// JsonException kódy (3001–3003)
	JSON_DEPTH: 3001,
	INVALID_JSON: 3002,
	JSON_UTF8: 3003,
} as const;

export type ErrorCodeValue = typeof ErrorCode[keyof typeof ErrorCode];

// ─── Message levels ───────────────────────────────────

export type MessageLevel = 'error' | 'success' | 'warning' | 'info';

export interface AppMessage {
	level: MessageLevel;
	title?: string;
	message: string;
	/** Původní zpráva ze serveru (zobrazena v debug módu jako sekundární info) */
	serverMessage?: string;
	detail?: unknown;
	/** false = neprovádět auto-hide (default: true) */
	autoHide?: boolean;
}

// ─── Errors ───────────────────────────────────────────

export interface ApiError {
	type: string;
	message: string;
	context?: unknown;
	code: number;
}

export interface AiBridgeError {
	type: 'network' | 'http' | 'api' | 'validation' | 'parse' | 'timeout' | 'dom' | 'unknown';
	/** Kód chyby – HTTP status nebo ErrorCode z AiException */
	code?: number;
	message: string;
	/** Původní zpráva ze serveru (PHP AiException message) – vždy přítomna pokud server vrátil chybu */
	serverMessage?: string;
	detail?: unknown;
	/** Zdroj chyby pro debugování ('ajax' | 'dom' | 'custom' | …) */
	source?: string;
}

// ─── SSE streaming events ─────────────────────────────

export interface SseProgressEvent {
	phase: string;
	message: string;
	current: number;
	total: number;
	phase_order?: number;
	phase_total?: number;
	elapsed?: number;
}

export interface SseResultEvent {
	index: number;
	html: string;
	parsed: unknown;
	key: string | null;
}

export interface SseCompleteEvent {
	success: boolean;
	total: number;
	duration: number;
	meta?: Record<string, unknown>;
}

export interface SseErrorEvent {
	message: string;
	code: number;
	type: string;
}

// ─── Transport modes ──────────────────────────────────

/**
 * Definované transport režimy.
 * `(string & {})` umožňuje rozšíření o vlastní režimy se zachováním autocomplete.
 */
export type TransportMode = 'http' | 'sse' | 'websocket' | (string & {});

// ─── Per-transport response types ─────────────────────

/**
 * Struktura JSON odpovědi z backendu (HTTP mód).
 * Přesně odpovídá tomu, co vrací PHP API handler.
 */
export interface ServerResponse<T = unknown> {
	api: {
		success: boolean;
		status_code: number;
		meta?: Record<string, unknown>;
		error?: ApiError;
	};
	provider: {
		success: boolean;
		status_code: number;
		meta?: Record<string, unknown>;
	} | null;
	data: {
		raw: unknown;
		parsed: T;
		html: string;
	} | null;
}

/** WebSocket response payload */
export interface WsResponseData<T = unknown> {
	data: T;
	messageId?: string;
}

// ─── Discriminated ApiResult union ────────────────────

export interface HttpResult<T = unknown> {
	mode: 'http';
	response: ServerResponse<T>;
}

export interface SseResult {
	mode: 'sse';
	response: SseCompleteEvent;
}

export interface WsResult<T = unknown> {
	mode: 'websocket';
	response: WsResponseData<T>;
}

/**
 * Diskriminovaný union – umožňuje typově bezpečné narrowing
 * přes `result.mode` pro každý transportní režim.
 *
 * @example
 * const result = await api.send(data);
 * if (result?.mode === 'http') {
 *     result.response.data?.html; // string
 * }
 */
export type ApiResult<T = unknown> =
	| HttpResult<T>
	| SseResult
	| WsResult<T>;

// ─── Transport interface ──────────────────────────────

export interface ApiTransport {
	readonly name: TransportMode;
	readonly priority: number;
	canHandle(): boolean;
	send(payload: Record<string, unknown>): Promise<ApiResult | null>;
	abort(): void;
}

// ─── Middleware ────────────────────────────────────────

/**
 * Kontext předávaný middleware pipeline.
 * `meta` slouží pro sdílení dat mezi middlewary (timing, cache info atd.).
 */
export interface ApiContext {
	payload: Record<string, unknown>;
	startTime: number;
	transport?: TransportMode;
	meta: Record<string, unknown>;
}

export type ApiMiddleware = (
	context: ApiContext,
	next: () => Promise<ApiResult | null>,
) => Promise<ApiResult | null>;
