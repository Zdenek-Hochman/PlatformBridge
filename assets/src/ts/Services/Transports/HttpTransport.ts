import { EventBus } from '@/Core';
import {
	type ApiTransport,
	type HttpResult,
	type ServerResponse,
	type AiBridgeError,
} from '@/Types';

// ─── Config ───────────────────────────────────────────

export interface HttpTransportConfig {
	url: string;
	timeout?: number;
	priority?: number;
	headers?: Record<string, string>;
}

const DEFAULTS = {
	timeout: 60_000,
	priority: 10,
	headers: {} as Record<string, string>,
};

// ─── Transport ────────────────────────────────────────

/**
 * HttpTransport – klasický POST fetch s JSON odpovědí.
 * Self-contained: obsahuje veškerou logiku odesílání, parsování i validace.
 * Chyby emituje přes EventBus ('error' event).
 */
export class HttpTransport implements ApiTransport {
	readonly name = 'http' as const;
	readonly priority: number;

	private readonly events: EventBus;
	private readonly url: string;
	private readonly timeout: number;
	private readonly headers: Record<string, string>;
	private controller?: AbortController;

	constructor(events: EventBus, config: HttpTransportConfig) {
		this.events = events;
		this.url = config.url;
		this.timeout = config.timeout ?? DEFAULTS.timeout;
		this.priority = config.priority ?? DEFAULTS.priority;
		this.headers = { ...DEFAULTS.headers, ...config.headers };
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
			if (!body) return null;

			if (!this.validate(response, body)) return null;

			return { mode: 'http', response: body };

		} catch (error) {
			if ((error as DOMException).name === 'AbortError') {
				this.emitError('network', 'Požadavek byl zrušen nebo vypršel timeout.');
			} else {
				this.emitError('network', 'Nelze se připojit k serveru.');
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
		try {
			return await response.json();
		} catch {
			this.emitError('parse', 'Server vrátil neočekávanou odpověď.');
			return null;
		}
	}

	private validate(response: Response, body: ServerResponse): boolean {
		if (!response.ok) {
			const message = body.api?.error?.message ?? `Chyba serveru (${response.status})`;
			this.emitError('http', message, response.status);
			return false;
		}

		if (body.api && !body.api.success && body.api.error) {
			this.emitError('http', body.api.error.message ?? 'API vrátila chybu.', body.api.error.code);
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

	private emitError(type: AiBridgeError['type'], message: string, statusCode?: number): void {
		this.events.publish('error', {
			error: { type, message },
			statusCode,
		});
	}
}
