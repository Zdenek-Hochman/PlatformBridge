import {
	type ValidationError,
	type AiBridgeError,
	type SseProgressEvent,
	type SseResultEvent,
	type SseCompleteEvent,
	type SseErrorEvent,
	type ApiResult,
	type TransportMode,
} from 'assets/ts/Types';

/**
 * EventBus - Emitování vlastních eventů pro cílovou aplikaci
 *
 * Umožňuje:
 * - Interní pub/sub systém (on / off / emit)
 * - Propagaci jako nativní CustomEvent na DOM elementu (window / vlastní target)
 * - Cílová aplikace může poslouchat přes addEventListener
 *
 * @example
 * Cílová aplikace
 * window.addEventListener('pb:success', (e: CustomEvent) => {
 *     console.log(e.detail); // { data, endpoint, duration }
 * });
 */

export type EventCallback<T = unknown> = (payload: T) => void;

export interface AiBridgeEventMap {
	'request:start': { payload: Record<string, unknown> };
	'loading':        { state: boolean; message?: string };
	'success':        { data: ApiResult; duration: number };
	'error':          { error: AiBridgeError; statusCode?: number };
	'validation':     { errors: ValidationError[] };
	'request:end':       { success: boolean; duration: number };
	'copy':           { success: boolean };
	'transport:fallback': { from: TransportMode; to: TransportMode };
	'regenerate-key': { key: string; index: number; data: ApiResult | null };
	'use':            { data: Record<string, string> };

	// SSE streaming events
	'sse:progress':   SseProgressEvent;
	'sse:result':     SseResultEvent;
	'sse:complete':   SseCompleteEvent;
	'sse:error':      SseErrorEvent;
}

export class EventBus {
	/** Interní listenery */
	private listeners = new Map<string, Set<EventCallback<any>>>();

	/** DOM element, na který se dispatchují CustomEventy */
	private target: EventTarget;

	/** Prefix pro DOM eventy */
	private readonly DOM_PREFIX = 'pb';

	constructor(target: EventTarget = window) {
		this.target = target;
	}

	/**
	 * Přihlásí callback na interní event.
	 *
	 * Umožňuje registrovat funkci, která bude volána při vyvolání dané události.
	 * Pokud pro daný event ještě neexistuje žádný listener, vytvoří novou množinu.
	 *
	 * @template K Název eventu podle AiBridgeEventMap
	 * @param event Název eventu, na který se má callback přihlásit
	 * @param callback Funkce, která bude volána při vyvolání eventu
	 * @returns this (umožňuje řetězení)
	 */
	subscribe<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		if (!this.listeners.has(event)) {
			this.listeners.set(event, new Set());
		}
		this.listeners.get(event)!.add(callback);
		return this;
	}

	/**
	 * Odhlásí callback z interního eventu.
	 *
	 * Odebere konkrétní callback z množiny listenerů pro daný event.
	 * Pokud callback není nalezen, nic se nestane.
	 *
	 * @template K Název eventu podle AiBridgeEventMap
	 * @param event Název eventu, ze kterého se má callback odhlásit
	 * @param callback Funkce, která se má odhlásit
	 * @returns this (umožňuje řetězení)
	 */
	unsubscribe<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		this.listeners.get(event)?.delete(callback);
		return this;
	}

	/**
	 * Jednorázový listener (callback bude zavolán pouze jednou).
	 *
	 * Po prvním vyvolání eventu se callback automaticky odhlásí.
	 *
	 * @template K Název eventu podle AiBridgeEventMap
	 * @param event Název eventu, na který se má callback přihlásit
	 * @param callback Funkce, která bude volána pouze při prvním vyvolání eventu
	 * @returns this (umožňuje řetězení)
	 */
	once<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		const wrapper: EventCallback<AiBridgeEventMap[K]> = (payload) => {
			this.unsubscribe(event, wrapper);
			callback(payload);
		};
		return this.subscribe(event, wrapper);
	}

	/**
	 * Publikuje (emitne) event interně i jako DOM CustomEvent.
	 *
	 * 1) Zavolá všechny interní listenery registrované přes subscribe/once.
	 * 2) Vytvoří a dispatchuje DOM CustomEvent s prefixem (např. "pb:success"),
	 *    který mohou poslouchat i externí aplikace přes addEventListener.
	 *
	 * Pokud některý listener vyhodí výjimku, je zachycena a zalogována do konzole,
	 * aby neovlivnila ostatní listenery.
	 *
	 * @template K Název eventu podle AiBridgeEventMap
	 * @param event Název eventu, který se má emitovat
	 * @param payload Data předávaná listenerům a v detailu CustomEventu
	 */
	publish<K extends keyof AiBridgeEventMap>(event: K, payload: AiBridgeEventMap[K]): void {
		// 1) Interní listenery
		const callbacks = this.listeners.get(event);
		if (callbacks) {
			for (const cb of callbacks) {
				try {
					cb(payload);
				} catch (err) {
					console.error(`[EventBus] Chyba v listeneru "${event}":`, err);
				}
			}
		}

		// 2) DOM CustomEvent - cílová aplikace může poslouchat přes addEventListener
		const domEvent = new CustomEvent(`${this.DOM_PREFIX}:${event}`, {
			detail: payload,
			bubbles: true,
			cancelable: false,
		});
		this.target.dispatchEvent(domEvent);
	}

	/**
	 * Odstraní všechny listenery pro všechny eventy.
	 *
	 * Po zavolání této metody nebude na žádný event zaregistrován žádný interní listener.
	 */
	clear(): void {
		this.listeners.clear();
	}

	/**
	 * Odstraní všechny listenery pouze pro konkrétní event.
	 *
	 * Po zavolání této metody nebude na daný event zaregistrován žádný interní listener.
	 *
	 * @template K Název eventu podle AiBridgeEventMap
	 * @param event Název eventu, jehož listenery se mají odstranit
	 */
	clearTopic<K extends keyof AiBridgeEventMap>(event: K): void {
		this.listeners.delete(event);
	}

	/** @alias publish */
	/** @deprecated */
	emit<K extends keyof AiBridgeEventMap>(event: K, payload: AiBridgeEventMap[K]): void {
		this.publish(event, payload);
	}

	/** @alias subscribe */
	/** @deprecated */
	on<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		return this.subscribe(event, callback);
	}

	/** @alias unsubscribe */
	/** @deprecated */
	off<K extends keyof AiBridgeEventMap>(event: K, callback: EventCallback<AiBridgeEventMap[K]>): this {
		return this.unsubscribe(event, callback);
	}
}
