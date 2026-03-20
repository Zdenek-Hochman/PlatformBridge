import { COMPONENTS, MODULE } from "assets/ts/Const";
import { Dom, DomNode, EventBus, ErrorHandler } from "assets/ts/Core";
import { ApiClient, ApiErrorHandler, SessionManager } from "assets/ts/Services";
import { FormValidator, VisibilityController, ResultActionHandler } from 'assets/ts/Features';
import { CustomSelect, LayoutController } from "assets/ts/UI";
// import { PlatformBridge } from 'assets/ts/Public/PlatformBridge';

import { HttpTransport, SseTransport } from 'assets/ts/Services/Transports';
import { RetryMiddleware, CacheMiddleware, TimingMiddleware } from 'assets/ts/Middleware';

export class App {
	private events!: EventBus;
	private api!: ApiClient;
	private session!: SessionManager;
	private validator!: FormValidator;
	private errorHandler!: ErrorHandler;
	private apiErrors!: ApiErrorHandler;

	private requestButton!: DomNode<HTMLElement>;
	private resultContainer!: DomNode<HTMLElement>;
	private form!: HTMLElement;

	constructor() {
		this.requestButton = Dom.action('send-request');
		this.resultContainer = Dom.component(COMPONENTS.RESULT_CONTAINER);
		this.form = Dom.qRequired('form');
	}

	private readonly pipeline = [
		{ name: "DOM", time: performance.now(), step: () => this.initDom() },
		{ name: "Core", time: performance.now(), step: () => this.initCore() },
		{ name: "Services", time: performance.now(), step: () => this.initServices() },
		{ name: "Features", time: performance.now(), step: () => this.initFeatures() },
		{ name: "Bindings", time: performance.now(), step: () => this.bindEvents() },
		// { name: "Public API", time: performance.now(), step: () => this.exposePublicApi() },
	];

	init(): void {
		for (const stage of this.pipeline) {
			console.log("Init: " + stage.name + " Time:", performance.now() - stage.time);
			stage.step();
		}
	}

	private initDom(): void {
		CustomSelect.init(`select.${MODULE.FIELD}`);
		LayoutController.autoInit();
		VisibilityController.init();
	}

	private initCore(): void {
		this.events = new EventBus(window);
		this.errorHandler = new ErrorHandler(this.events, { autoListen: true, debug: true });
		this.apiErrors = new ApiErrorHandler(this.events, this.errorHandler, { debug: true });
	}

	private initServices(): void {
		const apiUrl = this.getApiUrl();

		this.api = new ApiClient(this.events, { allowFallback: true })
			.registerTransport(new HttpTransport(this.events, {
				url: apiUrl,
				timeout: 60_000,
				priority: 10,
				apiErrorHandler: this.apiErrors,
			}))
			.use(RetryMiddleware(2))
			.use(CacheMiddleware(10_000));

		this.session = new SessionManager();

		new ResultActionHandler(this.events, this.api, this.session).init();
	}

	private initFeatures(): void {
		this.validator = new FormValidator(this.events, { requiredOnly: true });
	}

	private bindEvents(): void {
		this.requestButton.on('click', (e) => this.submitForm(e));
	}

	/**
	 * Vystaví veřejné API na window.PlatformBridge.
	 * Cílová aplikace může volat: window.PlatformBridge.setFieldValue(...) atd.
	 */
	// private exposePublicApi(): void {
	// 	const bridge = new PlatformBridge(
	// 		this.api,
	// 		this.events,
	// 		this.session,
	// 		this.validator,
	// 	);

	// 	(window as any).PlatformBridge = bridge;

	// 	// Dispatchnout event, aby cílová aplikace věděla, že API je připraveno
	// 	window.dispatchEvent(new CustomEvent('pb:ready', { detail: { bridge } }));
	// }

	/**
	 * Přečte API URL z data atributu na wrapper elementu.
	 * PHP strana injektuje správnou URL podle režimu (standalone/vendor).
	 */
	private getApiUrl(): string {
		const module = document.querySelector<HTMLElement>(`.${MODULE.ROOT}`);
		const url = module?.dataset.apiUrl;

		if (!url) {
			console.warn('[PlatformBridge] data-api-url not found on .ai-module, using fallback');
			// Fallback: produkce → public/platformbridge/api.php
			// Na localhost se data-api-url nastaví z PHP na resources/stubs/api.php
			return '/public/platformbridge/api.php';
		}

		return url;
	}

	private async submitForm(e: PointerEvent): Promise<void> {
		e.preventDefault();

		if (!this.validator.validate(this.form as HTMLFormElement)) {
			return;
		}

		const data = ApiClient.extractFormData(this.form as HTMLFormElement);
		// const result = await this.api.send(data);
		const result = await this.api.via('http').send(data);
		if (!result) return;

		this.session.save(data);

		switch (result.mode) {
			case 'http':
				if (result.response.data?.html) {
					this.resultContainer.html(result.response.data.html);
				}
				break;

			case 'sse':
				// SSE výsledky se renderují průběžně přes EventBus (sse:result)
				// result.response obsahuje finální SseCompleteEvent
				console.log(`[SSE] ${result.response.total} výsledků za ${result.response.duration}s`);
				break;

			case 'websocket':
				// WebSocket response handling
				console.log('[WS] Response:', result.response.data);
				break;
		}
	}
}
