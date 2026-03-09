import { EventBus } from '@/Core';
import {
	type ApiTransport,
	type WsResult,
	type WsResponseData,
	type AiBridgeError,
} from '@/Types';

// ─── Config ───────────────────────────────────────────

export interface WebSocketTransportConfig {
	url: string;
	priority?: number;
	protocols?: string[];
	timeout?: number;
}

const DEFAULTS = {
	priority: 5,
	protocols: [] as string[],
	timeout: 30_000,
};

// ─── Transport ────────────────────────────────────────

/**
 * WebSocketTransport – obousměrná komunikace přes WebSocket.
 * Připraveno pro budoucí rozšíření (real-time chat, long-running tasks).
 *
 * Pro každý send() otevře nové spojení, odešle payload jako JSON,
 * a čeká na první zprávu jako odpověď.
 */
export class WebSocketTransport implements ApiTransport {
	readonly name = 'websocket' as const;
	readonly priority: number;

	private readonly events: EventBus;
	private readonly url: string;
	private readonly protocols: string[];
	private readonly timeout: number;
	private socket: WebSocket | null = null;

	constructor(events: EventBus, config: WebSocketTransportConfig) {
		this.events = events;
		this.url = config.url;
		this.priority = config.priority ?? DEFAULTS.priority;
		this.protocols = config.protocols ?? DEFAULTS.protocols;
		this.timeout = config.timeout ?? DEFAULTS.timeout;
	}

	canHandle(): boolean {
		return typeof WebSocket !== 'undefined';
	}

	async send(payload: Record<string, unknown>): Promise<WsResult | null> {
		return new Promise((resolve) => {
			const timeoutId = setTimeout(() => {
				this.abort();
				this.emitError('network', 'WebSocket timeout.');
				resolve(null);
			}, this.timeout);

			try {
				this.socket = new WebSocket(this.url, this.protocols);

				this.socket.onopen = () => {
					this.socket!.send(JSON.stringify(payload));
				};

				this.socket.onmessage = (event) => {
					clearTimeout(timeoutId);
					try {
						const data = JSON.parse(event.data) as WsResponseData;
						this.socket?.close();
						resolve({ mode: 'websocket', response: data });
					} catch {
						this.socket?.close();
						this.emitError('parse', 'Neplatná WebSocket odpověď.');
						resolve(null);
					}
				};

				this.socket.onerror = () => {
					clearTimeout(timeoutId);
					this.socket?.close();
					this.emitError('network', 'WebSocket chyba připojení.');
					resolve(null);
				};

			} catch {
				clearTimeout(timeoutId);
				this.emitError('network', 'WebSocket nelze vytvořit.');
				resolve(null);
			}
		});
	}

	abort(): void {
		this.socket?.close();
		this.socket = null;
	}

	private emitError(type: AiBridgeError['type'], message: string): void {
		this.events.publish('error', {
			error: { type, message },
		});
	}
}
