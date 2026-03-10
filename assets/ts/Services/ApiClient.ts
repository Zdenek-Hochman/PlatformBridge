import { EventBus } from 'assets/ts/Core';
import {
	type ApiResult,
	type ApiMiddleware,
	type ApiTransport,
	type ApiContext,
	type TransportMode,
} from 'assets/ts/Types';
import { TransportRegistry } from './TransportRegistry';

// ─── Options ──────────────────────────────────────────

export interface ApiClientOptions {
	/** Povolit fallback na další transport pokud aktuální selže (výchozí: true) */
	allowFallback?: boolean;
}

// ─── RequestBuilder ───────────────────────────────────

/**
 * Scoped builder pro vynucení konkrétního transportu.
 *
 * @example
 * const result = await api.via('http').send(data);
 */
export class RequestBuilder {
	constructor(
		private readonly client: ApiClient,
		private readonly transport: TransportMode,
	) {}

	async send<T = unknown>(payload: Record<string, unknown>): Promise<ApiResult<T> | null> {
		return this.client.sendVia<T>(this.transport, payload);
	}
}

// ─── ApiClient ────────────────────────────────────────

/**
 * ApiClient – Sjednocený přístupový bod pro komunikaci s AI API.
 *
 * Podporuje:
 * - Fluent chaining: `api.registerTransport(...).use(...).use(...)`
 * - Automatický výběr transportu podle priority
 * - Vynucený transport: `api.via('http').send(data)`
 * - Middleware pipeline (onion model)
 * - Fallback na další transporty při selhání
 *
 * @example
 * const api = new ApiClient(events)
 *     .registerTransport(new HttpTransport(events, { url: '...', priority: 10 }))
 *     .registerTransport(new SseTransport(events, { url: '...', priority: 0 }))
 *     .use(RetryMiddleware(2))
 *     .use(CacheMiddleware(10_000));
 *
 * // Automatický výběr (SSE má nižší priority → zkusí se první)
 * const result = await api.send(data);
 *
 * // Vynucený HTTP transport
 * const httpResult = await api.via('http').send(data);
 */
export class ApiClient {
	private readonly options: Required<ApiClientOptions>;
	private readonly events: EventBus;
	private readonly registry: TransportRegistry;
	private readonly middlewares: ApiMiddleware[] = [];

	constructor(events: EventBus, options: ApiClientOptions = {}) {
		this.options = { allowFallback: true, ...options };
		this.events = events;
		this.registry = new TransportRegistry();
	}

	// ── Fluent configuration ─────────────────────────

	/** Zaregistruje transport (řadí se podle priority) */
	registerTransport(transport: ApiTransport): this {
		this.registry.register(transport);
		return this;
	}

	/** Odebere transport podle jména */
	unregisterTransport(name: TransportMode): this {
		this.registry.unregister(name);
		return this;
	}

	/** Přidá middleware do pipeline */
	use(middleware: ApiMiddleware): this {
		this.middlewares.push(middleware);
		return this;
	}

	// ── Transport access ─────────────────────────────

	/** Přímý přístup k transportu pro runtime konfiguraci */
	transport<T extends ApiTransport>(name: TransportMode): T | undefined {
		return this.registry.get(name) as T | undefined;
	}

	// ── Sending ──────────────────────────────────────

	/**
	 * Odešle payload přes middleware pipeline,
	 * automaticky vybere transport podle priority.
	 */
	async send<T = unknown>(payload: Record<string, unknown>): Promise<ApiResult<T> | null> {
		return this.execute<T>(payload);
	}

	/**
	 * Vrátí RequestBuilder s vynuceným transportem.
	 * Ostatní transporty slouží jako záloha (pokud je allowFallback true).
	 *
	 * @example
	 * const result = await api.via('http').send(data);
	 */
	via(transport: TransportMode): RequestBuilder {
		return new RequestBuilder(this, transport);
	}

	/**
	 * Interní metoda volaná z RequestBuilder.
	 * @internal
	 */
	async sendVia<T = unknown>(
		transportName: TransportMode,
		payload: Record<string, unknown>,
	): Promise<ApiResult<T> | null> {
		return this.execute<T>(payload, transportName);
	}

	/** Přeruší všechny probíhající požadavky */
	abort(): void {
		this.registry.abortAll();
	}

	/** Vrací true, pokud SSE transport právě streamuje */
	get isStreaming(): boolean {
		const sse = this.registry.get('sse') as { isStreaming?: boolean } | undefined;
		return sse?.isStreaming ?? false;
	}

	// ── Core execution ───────────────────────────────

	private async execute<T>(
		payload: Record<string, unknown>,
		forcedTransport?: TransportMode,
	): Promise<ApiResult<T> | null> {
		const context: ApiContext = {
			payload,
			startTime: performance.now(),
			meta: {},
		};

		this.events.publish('request:start', { payload });

		let success = false;

		try {
			const result = await this.compose(
				this.middlewares,
				() => this.dispatch(context, forcedTransport),
				context,
			);

			success = result !== null;

			if (result) {
				const duration = performance.now() - context.startTime;
				this.events.publish('success', { data: result, duration });
			}

			return result as ApiResult<T> | null;

		} finally {
			const duration = performance.now() - context.startTime;
			this.events.publish('request:end', { success, duration });
		}
	}

	// ── Middleware pipeline (onion model) ─────────────

	private compose(
		middlewares: ApiMiddleware[],
		handler: () => Promise<ApiResult | null>,
		context: ApiContext,
	): Promise<ApiResult | null> {
		let index = -1;

		const dispatch = (i: number): Promise<ApiResult | null> => {
			if (i <= index) {
				throw new Error('next() called multiple times');
			}

			index = i;
			const mw = middlewares[i];

			return mw
				? mw(context, () => dispatch(i + 1))
				: handler();
		};

		return dispatch(0);
	}

	// ── Transport dispatch ───────────────────────────

	private async dispatch(
		context: ApiContext,
		forcedTransport?: TransportMode,
	): Promise<ApiResult | null> {
		const available = this.registry.getAvailable();

		if (available.length === 0) {
			console.warn('[ApiClient] Žádné dostupné transporty.');
			return null;
		}

		return forcedTransport
			? this.dispatchForced(context, available, forcedTransport)
			: this.dispatchByPriority(context, available);
	}

	/**
	 * Vynucený transport – zkusí specifikovaný první,
	 * ostatní jako fallback pokud allowFallback === true.
	 */
	private async dispatchForced(
		context: ApiContext,
		available: ApiTransport[],
		forced: TransportMode,
	): Promise<ApiResult | null> {
		// Zkus vynucený transport
		const primary = available.find((t) => t.name === forced);

		if (primary) {
			context.transport = primary.name;
			const result = await primary.send(context.payload);
			if (result) return result;
		}

		// Fallback na ostatní (pokud povoleno)
		if (!this.options.allowFallback) return null;

		const fallbacks = available.filter((t) => t.name !== forced);

		for (const transport of fallbacks) {
			this.events.publish('transport:fallback', {
				from: forced,
				to: transport.name,
			});

			context.transport = transport.name;
			const result = await transport.send(context.payload);
			if (result) return result;
		}

		return null;
	}

	/**
	 * Automatický výběr – prochází transporty podle priority (nižší = vyšší priorita).
	 */
	private async dispatchByPriority(
		context: ApiContext,
		available: ApiTransport[],
	): Promise<ApiResult | null> {
		for (let i = 0; i < available.length; i++) {
			const transport = available[i];
			context.transport = transport.name;

			const result = await transport.send(context.payload);
			if (result) return result;

			// Transport selhal — fallback
			if (!this.options.allowFallback) return null;

			const next = available[i + 1];
			if (next) {
				this.events.publish('transport:fallback', {
					from: transport.name,
					to: next.name,
				});
			}
		}

		return null;
	}

	// ── Form data extraction (static utility) ────────

	static extractFormData(form: HTMLFormElement): Record<string, unknown> {
		const elements = Array.from(form.elements) as HTMLElement[];

		return elements
			.filter(
				(el): el is HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement => {
					if (
						!(
							el instanceof HTMLInputElement ||
							el instanceof HTMLSelectElement ||
							el instanceof HTMLTextAreaElement
						)
					) {
						return false;
					}
					return !!el.name && !el.disabled && el.type !== 'button' && el.type !== 'submit';
				},
			)
			.reduce<Record<string, unknown>>((data, el) => {
				if (el instanceof HTMLInputElement) {
					if (el.type === 'checkbox') {
						data[el.name] = el.checked;
					} else if (el.type === 'radio') {
						if (el.checked) data[el.name] = el.value;
					} else {
						data[el.name] = el.value;
					}
				} else {
					data[el.name] = el.value;
				}
				return data;
			}, {});
	}
}
