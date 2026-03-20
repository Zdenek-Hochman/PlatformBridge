import { EventBus } from 'assets/ts/Core';
import {
	type ApiTransport,
	type HttpResult,
	type ServerResponse,
} from 'assets/ts/Types';
import { ApiErrorHandler } from '../ApiErrorHandler';

// ─── Config ───────────────────────────────────────────

export interface HttpTransportConfig {
	url: string;
	timeout?: number;
	priority?: number;
	headers?: Record<string, string>;
	/** ApiErrorHandler pro delegaci chyb (volitelný – bez něj se chyby logují do konzole) */
	apiErrorHandler?: ApiErrorHandler;
}

const DEFAULTS = {
	timeout: 60_000,
	priority: 10,
	headers: {} as Record<string, string>,
};

// ─── Transport ────────────────────────────────────────

/**
 * HttpTransport – klasický POST fetch s JSON odpovědí.
 * Self-contained: obsahuje veškerou logiku odesílání a parsování.
 * Chyby deleguje na ApiErrorHandler (pokud je poskytnut),
 * jinak emituje raw error eventy přes EventBus.
 */
export class HttpTransport implements ApiTransport {
	readonly name = 'http' as const;
	readonly priority: number;

	private readonly events: EventBus;
	private readonly url: string;
	private readonly timeout: number;
	private readonly headers: Record<string, string>;
	private readonly apiErrors?: ApiErrorHandler;
	private controller?: AbortController;

	constructor(events: EventBus, config: HttpTransportConfig) {
		this.events = events;
		this.url = config.url;
		this.timeout = config.timeout ?? DEFAULTS.timeout;
		this.priority = config.priority ?? DEFAULTS.priority;
		this.headers = { ...DEFAULTS.headers, ...config.headers };
		this.apiErrors = config.apiErrorHandler;
	}

	canHandle(): boolean {
		return true; // HTTP je vždy dostupný
	}

	async send(payload: Record<string, unknown>): Promise<HttpResult | null> {
		this.abort();
		this.controller = new AbortController();

		try {
			const response = await fetch(this.url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...this.headers },
				body: JSON.stringify(payload),
				signal: this.createSignal(),
			});

			const body = await this.parseJson(response);

			// ApiErrorHandler přebírá veškerou logiku zpracování chyb
			if (this.apiErrors) {
				if (!body) {
					this.apiErrors.handleUnexpectedResponse(response);
					return null;
				}

				const error = this.apiErrors.handleResponse(response, body);
				if (error) return null;
			} else {
				// Fallback bez ApiErrorHandler (zpětná kompatibilita)
				if (!body) return null;
				if (!this.validateLegacy(response, body)) return null;
			}

			return { mode: 'http', response: body };

		} catch (error) {
			if (this.apiErrors) {
				this.apiErrors.handleTransportError(error);
			}
			return null;

		} finally {
			this.controller = undefined;
		}
	}

	abort(): void {
		this.controller?.abort();
		this.controller = undefined;
	}

	// ─── Private ──────────────────────────────────────

	private async parseJson(response: Response): Promise<ServerResponse | null> {
		return await response.json();
	}

	/** @deprecated Zpětná kompatibilita – použijte ApiErrorHandler */
	private validateLegacy(response: Response, body: ServerResponse): boolean {
		if (!response.ok) {
			const message = body.api?.error?.message ?? `Chyba serveru (${response.status})`;
			return false;
		}

		if (body.api && !body.api.success && body.api.error) {
			return false;
		}

		return true;
	}

	private createSignal(): AbortSignal {
		return AbortSignal.any([
			this.controller!.signal,
			AbortSignal.timeout(this.timeout),
		]);
	}
}
