import { COMPONENTS } from "@/Const";
import { Dom, DomNode, EventBus } from "@/Core";
import { ApiClient, SessionManager } from "@/Services";
import { FormValidator, VisibilityController, ResultActionHandler } from '@/Features';
import { CustomSelect, LayoutController } from "@/UI";

import { HttpTransport, SseTransport } from '@/Services/Transports';
import { RetryMiddleware, CacheMiddleware, TimingMiddleware } from '@/Middleware';

export class App {
	private events!: EventBus;
	private api!: ApiClient;
	private session!: SessionManager;
	private validator!: FormValidator;

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
	];

	init(): void {
		for (const stage of this.pipeline) {
			console.log("Init: " + stage.name + " Time:", performance.now() - stage.time);
			stage.step();
		}
	}

	private initDom(): void {
		CustomSelect.init();
		LayoutController.autoInit();
		VisibilityController.init();
	}

	private initCore(): void {
		this.events = new EventBus(window);
	}

	private initServices(): void {
		this.api = new ApiClient(this.events, { allowFallback: true })
			.registerTransport(new HttpTransport(this.events, {
				url: './src/PlatformBridge/AI/API/ApiHandler.php',
				timeout: 60_000,
				priority: 10,
			}))
			.registerTransport(new SseTransport(this.events, {
				url: './src/PlatformBridge/AI/API/ApiHandler.php',
				timeout: 60_000,
				priority: 10,
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
