import { EventBus } from '@/Core';
import {
	type ApiTransport,
	type SseResult,
	type ServerResponse,
	type SseProgressEvent,
	type SseResultEvent,
	type SseCompleteEvent,
	type SseErrorEvent,
	type AiBridgeError,
} from '@/Types';

// ─── Config ───────────────────────────────────────────

export interface SseTransportConfig {
	url: string;
	timeout?: number;
	priority?: number;
	headers?: Record<string, string>;
	enabled?: boolean;
}

interface ParsedSseEvent {
	type: string;
	data: unknown;
	id?: string;
}

const DEFAULTS = {
	timeout: 120_000,
	priority: 0,
	headers: {} as Record<string, string>,
	enabled: true,
};

// ─── Transport ────────────────────────────────────────

/**
 * SseTransport – Server-Sent Events streaming transport.
 * Self-contained: provádí fetch, čte ReadableStream, parsuje SSE eventy.
 *
 * Průběžně emituje přes EventBus:
 *  - 'sse:progress' — průběh generování
 *  - 'sse:result'   — jednotlivý výsledek (HTML fragment)
 *  - 'sse:complete'  — konec streamu
 *  - 'sse:error'    — chyba ze serveru
 *
 * Pokud server vrátí JSON místo SSE, automaticky použije JSON fallback.
 */
export class SseTransport implements ApiTransport {
	readonly name = 'sse' as const;
	readonly priority: number;

	private readonly events: EventBus;
	private readonly url: string;
	private readonly timeout: number;
	private readonly headers: Record<string, string>;
	private enabled: boolean;
	private controller: AbortController | null = null;
	private _isStreaming = false;

	constructor(events: EventBus, config: SseTransportConfig) {
		this.events = events;
		this.url = config.url;
		this.timeout = config.timeout ?? DEFAULTS.timeout;
		this.priority = config.priority ?? DEFAULTS.priority;
		this.headers = { ...DEFAULTS.headers, ...config.headers };
		this.enabled = config.enabled ?? DEFAULTS.enabled;
	}

	get isStreaming(): boolean {
		return this._isStreaming;
	}

	setEnabled(value: boolean): void {
		this.enabled = value;
	}

	canHandle(): boolean {
		return this.enabled && typeof EventSource !== 'undefined';
	}

	async send(payload: Record<string, unknown>): Promise<SseResult | null> {
		if (this._isStreaming) this.abort();

		this._isStreaming = true;
		this.controller = new AbortController();

		const timeoutId = setTimeout(() => this.controller?.abort(), this.timeout);

		try {
			const response = await fetch(this.url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'text/event-stream',
					...this.headers,
				},
				body: JSON.stringify(payload),
				signal: this.controller.signal,
			});

			clearTimeout(timeoutId);

			// Server vrátil JSON místo SSE streamu → JSON fallback
			const contentType = response.headers.get('content-type') ?? '';
			if (!contentType.includes('text/event-stream')) {
				return this.handleJsonFallback(response);
			}

			if (!response.ok) {
				this.emitError('http', `SSE chyba serveru (${response.status})`, response.status);
				return null;
			}

			if (!response.body) {
				this.emitError('network', 'ReadableStream není podporován.');
				return null;
			}

			const complete = await this.readStream(response.body);
			if (!complete) return null;

			return { mode: 'sse', response: complete };

		} catch (error) {
			clearTimeout(timeoutId);

			if ((error as DOMException).name === 'AbortError') {
				this.emitError('network', 'Stream byl zrušen nebo vypršel timeout.');
			} else {
				this.emitError('network', (error as Error).message);
			}
			return null;

		} finally {
			this._isStreaming = false;
			this.controller = null;
		}
	}

	abort(): void {
		this.controller?.abort();
		this.controller = null;
		this._isStreaming = false;
	}

	// ─── Stream reading ───────────────────────────────

	private async readStream(body: ReadableStream<Uint8Array>): Promise<SseCompleteEvent | null> {
		const reader = body.getReader();
		const decoder = new TextDecoder();
		let buffer = '';
		let completeEvent: SseCompleteEvent | null = null;

		try {
			while (true) {
				const { done, value } = await reader.read();
				if (done) break;

				buffer += decoder.decode(value, { stream: true });
				const events = buffer.split('\n\n');
				buffer = events.pop() ?? '';

				for (const rawEvent of events) {
					if (!rawEvent.trim()) continue;
					const parsed = this.parseSseEvent(rawEvent);
					if (!parsed) continue;

					const result = this.dispatchSseEvent(parsed);
					if (result !== null) completeEvent = result;
				}
			}

			// Zpracuj zbytek v bufferu
			if (buffer.trim()) {
				const parsed = this.parseSseEvent(buffer);
				if (parsed) {
					const result = this.dispatchSseEvent(parsed);
					if (result !== null) completeEvent = result;
				}
			}

		} finally {
			reader.releaseLock();
		}

		return completeEvent;
	}

	private parseSseEvent(raw: string): ParsedSseEvent | null {
		const lines = raw.split('\n');
		let type = 'message';
		const dataLines: string[] = [];
		let id: string | undefined;

		for (const line of lines) {
			if (line.startsWith('event: ')) type = line.slice(7).trim();
			else if (line.startsWith('data: ')) dataLines.push(line.slice(6));
			else if (line.startsWith('id: ')) id = line.slice(4).trim();
		}

		if (dataLines.length === 0) return null;

		const dataStr = dataLines.join('\n');

		try {
			return { type, data: JSON.parse(dataStr), id };
		} catch {
			return { type, data: dataStr, id };
		}
	}

	private dispatchSseEvent(event: ParsedSseEvent): SseCompleteEvent | null {
		switch (event.type) {
			case 'progress':
				this.events.publish('sse:progress', event.data as SseProgressEvent);
				return null;

			case 'result':
				this.events.publish('sse:result', event.data as SseResultEvent);
				return null;

			case 'complete': {
				const data = event.data as SseCompleteEvent;
				this.events.publish('sse:complete', data);
				return data;
			}

			case 'error':
				this.events.publish('sse:error', event.data as SseErrorEvent);
				return null;

			case 'keepalive':
				return null;

			default:
				console.warn('[SseTransport] Neznámý SSE event:', event.type);
				return null;
		}
	}

	// ─── JSON fallback ────────────────────────────────

	private async handleJsonFallback(response: Response): Promise<SseResult | null> {
		try {
			const body: ServerResponse = await response.json();

			if (!response.ok) {
				const message = body.api?.error?.message ?? `Chyba serveru (${response.status})`;
				this.emitError('http', message, response.status);
				return null;
			}

			if (body.api && !body.api.success && body.api.error) {
				this.emitError('http', body.api.error.message ?? 'API vrátila chybu.');
				return null;
			}

			// Emituj výsledek jako SSE-like event
			if (body.data?.html) {
				this.events.publish('sse:result', {
					index: 0,
					html: body.data.html,
					parsed: body.data.parsed,
					key: null,
				});
			}

			const complete: SseCompleteEvent = {
				success: true,
				total: 1,
				duration: 0,
				meta: body.provider?.meta as Record<string, unknown> ?? {},
			};

			this.events.publish('sse:complete', complete);
			return { mode: 'sse', response: complete };

		} catch {
			this.emitError('parse', 'Server vrátil neočekávanou odpověď.');
			return null;
		}
	}

	// ─── Error helper ─────────────────────────────────

	private emitError(type: AiBridgeError['type'], message: string, statusCode?: number): void {
		this.events.publish('error', {
			error: { type, message },
			statusCode,
		});
	}
}
