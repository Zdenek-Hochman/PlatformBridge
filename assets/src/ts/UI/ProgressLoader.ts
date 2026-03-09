/**
 * ProgressLoader – UI komponenta pro zobrazení průběhu SSE streamingu.
 *
 * Zobrazuje:
 * - Aktuální fázi (init, validating, sending, processing, rendering, complete)
 * - Progress bar s počtem dokončených odpovědí
 * - Textovou zprávu o aktuálním stavu
 * - Postupně se zobrazující výsledky
 *
 * Komponenta se napojuje na EventBus a reaguje na SSE eventy:
 *  - sse:progress → aktualizace fáze a progress baru
 *  - sse:result   → přidání hotového výsledku do kontejneru
 *  - sse:complete → dokončení s animací
 *  - sse:error    → zobrazení chyby
 *
 * Použití:
 *   const progress = new ProgressLoader(events);
 *   progress.mount(resultContainer);
 *   // ... SSE streaming probíhá ...
 *   progress.unmount(); // cleanup
 */

import { EventBus } from '../Core/EventBus';
import { Dom, DomNode } from '../Core/Dom';
import type { SseProgressEvent, SseResultEvent, SseCompleteEvent, SseErrorEvent } from '@/Types';

// ─── Konfigurace ───────────────────────────────────────────────

export interface ProgressLoaderOptions {
	/** Prefix pro CSS třídy */
	cssPrefix?: string;
	/** Animovat příchod výsledků */
	animateResults?: boolean;
	/** Delay animace mezi výsledky v ms */
	animationDelay?: number;
	/** Zobrazit časovač */
	showTimer?: boolean;
	/** Zobrazit čísla fází */
	showPhaseNumbers?: boolean;
}

const DEFAULTS: Required<ProgressLoaderOptions> = {
	cssPrefix: 'ai-progress',
	animateResults: true,
	animationDelay: 150,
	showTimer: true,
	showPhaseNumbers: true,
};

// ─── Fáze a jejich metadata ────────────────────────────────────

interface PhaseInfo {
	key: string;
	label: string;
	icon: string;
}

const PHASES: PhaseInfo[] = [
	{ key: 'init',        label: 'Inicializace',    icon: '⚙️' },
	{ key: 'validating',  label: 'Ověřování',       icon: '🔍' },
	{ key: 'preparing',   label: 'Příprava',        icon: '📋' },
	{ key: 'sending',     label: 'Odesílání',       icon: '📡' },
	{ key: 'processing',  label: 'Zpracování',      icon: '🤖' },
	{ key: 'rendering',   label: 'Renderování',     icon: '🎨' },
	{ key: 'complete',    label: 'Hotovo',           icon: '✅' },
];

export class ProgressLoader {
	private readonly options: Required<ProgressLoaderOptions>;
	private readonly events: EventBus;

	/** Root element loaderu */
	private root: DomNode<HTMLDivElement> | null = null;

	/** Reference na podřízené elementy */
	private els: {
		phases: DomNode<HTMLDivElement> | null;
		message: DomNode<HTMLDivElement> | null;
		bar: DomNode<HTMLDivElement> | null;
		barFill: DomNode<HTMLDivElement> | null;
		counter: DomNode<HTMLSpanElement> | null;
		timer: DomNode<HTMLSpanElement> | null;
		results: DomNode<HTMLDivElement> | null;
	} = {
		phases: null,
		message: null,
		bar: null,
		barFill: null,
		counter: null,
		timer: null,
		results: null,
	};

	/** Timer interval */
	private timerInterval: ReturnType<typeof setInterval> | null = null;
	private startTime = 0;

	/** Aktuální stav */
	private currentPhase = '';
	private completedCount = 0;
	private totalCount = 0;

	/** Reference na event handlery pro cleanup */
	private handlers: Array<{ event: string; handler: (...args: any[]) => void }> = [];

	constructor(events: EventBus, options: ProgressLoaderOptions = {}) {
		this.events = events;
		this.options = { ...DEFAULTS, ...options };
	}

	// ─── Lifecycle ─────────────────────────────────────────────────

	/**
	 * Připojí loader do cílového kontejneru a začne poslouchat eventy.
	 */
	mount(container: HTMLElement, totalCount: number = 1): void {
		this.unmount();

		this.totalCount = totalCount;
		this.completedCount = 0;
		this.currentPhase = '';
		this.startTime = Date.now();

		// Vytvořit DOM strukturu
		this.root = this.createDom();

		// Připojit do kontejneru
		container.innerHTML = '';
		Dom.wrap(container).append(this.root);

		// Spustit timer
		if (this.options.showTimer) {
			this.startTimer();
		}

		// Navěsit EventBus listenery
		this.bindEvents();
	}

	/**
	 * Odpojí loader a vyčistí eventy.
	 */
	unmount(): void {
		this.stopTimer();
		this.unbindEvents();

		if (this.root) {
			this.root.remove();
			this.root = null;
		}

		this.els = {
			phases: null,
			message: null,
			bar: null,
			barFill: null,
			counter: null,
			timer: null,
			results: null,
		};
	}

	/**
	 * Vrátí kontejner pro výsledky (pro přímý přístup).
	 */
	getResultsContainer(): HTMLElement | null {
		return this.els.results?.el ?? null;
	}

	// ─── DOM ───────────────────────────────────────────────────────

	private cx(element?: string, modifier?: string): string {
		const base = this.options.cssPrefix;
		if (!element) return modifier ? `${base}--${modifier}` : base;
		return modifier ? `${base}__${element}--${modifier}` : `${base}__${element}`;
	}

	private createDom(): DomNode<HTMLDivElement> {
		const root = Dom.create('div', { className: this.cx() });

		// ─── Header s fázemi
		const header = Dom.create('div', { className: this.cx('header') });

		// Fáze (stepper)
		this.els.phases = Dom.create('div', { className: this.cx('phases') });
		for (const phase of PHASES) {
			const phaseEl = Dom.create('div', {
				className: [this.cx('phase'), this.cx('phase', 'pending')],
				data: { phase: phase.key },
			});

			const icon = Dom.create('span', {
				className: this.cx('phase-icon'),
				text: phase.icon,
			});

			const label = Dom.create('span', {
				className: this.cx('phase-label'),
				text: phase.label,
			});

			phaseEl.append(icon, label);
			this.els.phases.append(phaseEl);
		}

		header.append(this.els.phases);

		// ─── Progress bar
		const barWrapper = Dom.create('div', { className: this.cx('bar-wrapper') });

		const barInfo = Dom.create('div', { className: this.cx('bar-info') });
		this.els.message = Dom.create('div', { className: this.cx('message'), text: 'Připravuji...' });
		this.els.counter = Dom.create('span', { className: this.cx('counter'), text: '0/0' });

		if (this.options.showTimer) {
			this.els.timer = Dom.create('span', { className: this.cx('timer'), text: '0.0s' });
			barInfo.append(this.els.message, this.els.counter, this.els.timer);
		} else {
			barInfo.append(this.els.message, this.els.counter);
		}

		this.els.bar = Dom.create('div', { className: this.cx('bar') });
		this.els.barFill = Dom.create('div', { className: this.cx('bar-fill') });
		this.els.barFill.css('width', '0%');
		this.els.bar.append(this.els.barFill);

		barWrapper.append(barInfo, this.els.bar);

		// ─── Results container
		this.els.results = Dom.create('div', { className: this.cx('results') });

		// Sestavit root
		root.append(header, barWrapper, this.els.results);

		return root;
	}

	// ─── Event Handlers ────────────────────────────────────────────

	private bindEvents(): void {
		this.listen('sse:progress', (data: SseProgressEvent) => this.onProgress(data));
		this.listen('sse:result', (data: SseResultEvent) => this.onResult(data));
		this.listen('sse:complete', (data: SseCompleteEvent) => this.onComplete(data));
		this.listen('sse:error', (data: SseErrorEvent) => this.onError(data));
	}

	private listen(event: string, handler: (...args: any[]) => void): void {
		(this.events as any).on(event, handler);
		this.handlers.push({ event, handler });
	}

	private unbindEvents(): void {
		for (const { event, handler } of this.handlers) {
			(this.events as any).off(event, handler);
		}
		this.handlers = [];
	}

	// ─── Progress ──────────────────────────────────────────────────

	private onProgress(data: SseProgressEvent): void {
		// Aktualizovat fázi
		if (data.phase !== this.currentPhase) {
			this.setActivePhase(data.phase);
			this.currentPhase = data.phase;
		}

		// Aktualizovat zprávu
		if (this.els.message) {
			this.els.message.text(data.message);
		}

		// Aktualizovat counter
		this.totalCount = data.total || this.totalCount;
		if (this.els.counter && this.totalCount > 0) {
			this.els.counter.text(`${data.current}/${data.total}`);
		}

		// Aktualizovat progress bar
		this.updateProgressBar(data.current, data.total);
	}

	private onResult(data: SseResultEvent): void {
		this.completedCount++;

		if (!this.els.results) return;

		// Vytvořit wrapper pro výsledek
		const resultEl = Dom.create('div', {
			className: [
				this.cx('result-item'),
				this.options.animateResults ? this.cx('result-item', 'entering') : '',
			].filter(Boolean),
			data: { index: String(data.index) },
		});

		resultEl.html(data.html);

		this.els.results.append(resultEl);

		// Animace vstupu
		if (this.options.animateResults) {
			const delay = data.index * this.options.animationDelay;
			setTimeout(() => {
				resultEl.removeClass(this.cx('result-item', 'entering'));
				resultEl.addClass(this.cx('result-item', 'visible'));
			}, Math.max(delay, 50));
		}
	}

	private onComplete(data: SseCompleteEvent): void {
		this.setActivePhase('complete');

		if (this.els.message) {
			this.els.message.text(`Hotovo! ${data.total} výsledků za ${data.duration}s`);
		}

		if (this.els.barFill) {
			this.els.barFill.css('width', '100%');
		}

		if (this.root) {
			this.root.addClass(this.cx(undefined, 'complete'));
		}

		this.stopTimer();
	}

	private onError(data: SseErrorEvent): void {
		this.setActivePhase('error');

		if (this.els.message) {
			this.els.message.text(`Chyba: ${data.message}`);
			this.els.message.addClass(this.cx('message', 'error'));
		}

		if (this.root) {
			this.root.addClass(this.cx(undefined, 'error'));
		}

		this.stopTimer();
	}

	// ─── Helpers ───────────────────────────────────────────────────

	private setActivePhase(phaseKey: string): void {
		if (!this.els.phases) return;

		const phaseEls = this.els.phases.findAll(`.${this.cx('phase')}`);
		let foundActive = false;

		phaseEls.each((node) => {
			const key = node.data('phase');
			const isError = phaseKey === 'error';

			if (key === phaseKey && !isError) {
				node.removeClass(this.cx('phase', 'pending'));
				node.removeClass(this.cx('phase', 'done'));
				node.addClass(this.cx('phase', 'active'));
				foundActive = true;
			} else if (!foundActive) {
				// Fáze před aktivní = done
				node.removeClass(this.cx('phase', 'pending'));
				node.removeClass(this.cx('phase', 'active'));
				node.addClass(this.cx('phase', 'done'));
			} else {
				// Fáze po aktivní = pending
				node.removeClass(this.cx('phase', 'active'));
				node.removeClass(this.cx('phase', 'done'));
				node.addClass(this.cx('phase', 'pending'));
			}

			if (isError) {
				if (key === phaseKey || node.hasClass(this.cx('phase', 'active'))) {
					node.addClass(this.cx('phase', 'error'));
				}
			}
		});
	}

	private updateProgressBar(current: number, total: number): void {
		if (!this.els.barFill || total <= 0) return;

		const percent = Math.min(100, Math.round((current / total) * 100));
		this.els.barFill.css('width', `${percent}%`);
	}

	private startTimer(): void {
		this.startTime = Date.now();

		this.timerInterval = setInterval(() => {
			if (!this.els.timer) return;
			const elapsed = (Date.now() - this.startTime) / 1000;
			this.els.timer.text(`${elapsed.toFixed(1)}s`);
		}, 100);
	}

	private stopTimer(): void {
		if (this.timerInterval) {
			clearInterval(this.timerInterval);
			this.timerInterval = null;
		}
	}
}
