/**
 * MessageRenderer – Vykreslování notifikací (error, success, warning, info)
 *
 * Používá BEM strukturu z NOTIFICATION konstant a _notification.scss.
 * Podporuje:
 * - Více úrovní zpráv (error, success, warning, info)
 * - Auto-hide s leaving animací
 * - Manuální zavření
 * - Verbose mód s detail blokem
 * - Maximální počet simultánních notifikací
 * - Lazy inicializaci kontejneru
 */

import { NOTIFICATION } from 'assets/ts/Const';
import { type MessageLevel, type AppMessage } from 'assets/ts/Types';

// ─── SVG Icons ──────────────────────────────────────────

const LEVEL_ICONS: Record<MessageLevel, string> = {
	error:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M7 7l6 6M13 7l-6 6" stroke-linecap="round"/></svg>',
	success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.5l2.5 2.5 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
	warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 3L2 17h16L10 3z" stroke-linejoin="round"/><path d="M10 8v4" stroke-linecap="round"/><circle cx="10" cy="14.5" r=".75" fill="currentColor" stroke="none"/></svg>',
	info:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M10 9v5" stroke-linecap="round"/><circle cx="10" cy="6.5" r=".75" fill="currentColor" stroke="none"/></svg>',
};

const CLOSE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>';

/** CSS modifikátor podle úrovně zprávy */
const LEVEL_MODIFIER: Record<MessageLevel, string> = {
	error:   NOTIFICATION.ERROR,
	success: NOTIFICATION.SUCCESS,
	warning: NOTIFICATION.WARNING,
	info:    NOTIFICATION.INFO,
};

/** Výchozí titulky podle úrovně */
const DEFAULT_TITLES: Record<MessageLevel, string> = {
	error:   'Chyba',
	success: 'Úspěch',
	warning: 'Varování',
	info:    'Informace',
};

// ─── Options ────────────────────────────────────────────

export interface MessageRendererOptions {
	/** Maximální počet současných notifikací (default: 5) */
	maxVisible?: number;
	/** Auto-hide delay v ms, 0 = žádný auto-hide (default: 8000) */
	autoHideDelay?: number;
	/** Zobrazit verbose detail blok (default: false) */
	verbose?: boolean;
	/**
	 * Debug mód – zobrazuje serverMessage blok a detail
	 * s kontextem chyby v notifikacích. (default: false)
	 */
	debug?: boolean;
}

const DEFAULTS: Required<MessageRendererOptions> = {
	maxVisible: 5,
	autoHideDelay: 8_000,
	verbose: false,
	debug: false,
};

// ─── Renderer ───────────────────────────────────────────

export class MessageRenderer {
	private readonly options: Required<MessageRendererOptions>;
	private container: HTMLElement | null = null;
	private readonly timers = new Map<HTMLElement, number>();
	private active: HTMLElement[] = [];

	constructor(options: MessageRendererOptions = {}) {
		this.options = { ...DEFAULTS, ...options };
	}

	// ─── Public API ─────────────────────────────────────

	/**
	 * Zobrazí notifikaci a vrátí její element.
	 */
	show(message: AppMessage): HTMLElement {
		const container = this.ensureContainer();
		this.enforceLimit();

		const el = this.build(message);
		container.appendChild(el);
		this.active.push(el);

		const delay = message.autoHide === false ? 0 : this.options.autoHideDelay;
		if (delay > 0) {
			const timer = window.setTimeout(() => this.dismiss(el), delay);
			this.timers.set(el, timer);
		}

		return el;
	}

	/**
	 * Odstraní konkrétní notifikaci s leaving animací.
	 */
	dismiss(el: HTMLElement): void {
		if (!el.parentNode) return;

		// Clear associated timer
		const timer = this.timers.get(el);
		if (timer) {
			clearTimeout(timer);
			this.timers.delete(el);
		}

		// Trigger leaving animation
		el.classList.add(NOTIFICATION.LEAVING);

		const remove = () => {
			el.remove();
			this.active = this.active.filter(n => n !== el);
		};

		el.addEventListener('animationend', remove, { once: true });
		// Fallback pokud animace nefiruje (display:none, reduced-motion…)
		setTimeout(remove, 500);
	}

	/**
	 * Odstraní všechny aktivní notifikace.
	 */
	clear(): void {
		for (const el of [...this.active]) {
			this.dismiss(el);
		}
	}

	// ─── Private ────────────────────────────────────────

	/**
	 * Zajistí existenci `.pb-notifications` kontejneru.
	 * Vytvoří ho lazy při prvním volání show().
	 */
	private ensureContainer(): HTMLElement {
		if (this.container && document.body.contains(this.container)) {
			return this.container;
		}

		// Zkus najít existující
		let container = document.querySelector<HTMLElement>(`.${NOTIFICATION.CONTAINER}`);

		if (!container) {
			container = document.createElement('div');
			container.className = NOTIFICATION.CONTAINER;
			document.body.appendChild(container);
		}

		this.container = container;
		return container;
	}

	/**
	 * Odstraní nejstarší notifikace pokud je překročen limit.
	 */
	private enforceLimit(): void {
		while (this.active.length >= this.options.maxVisible) {
			const oldest = this.active[0];
			if (oldest) this.dismiss(oldest);
		}
	}

	/**
	 * Sestaví DOM element notifikace.
	 */
	private build(message: AppMessage): HTMLElement {
		const el = document.createElement('div');
		el.className = `${NOTIFICATION.ROOT} ${LEVEL_MODIFIER[message.level]}`;
		el.setAttribute('role', message.level === 'error' ? 'alert' : 'status');

		const title = this.escapeHtml(message.title || DEFAULT_TITLES[message.level]);
		const text = this.escapeHtml(message.message);

		// Server message – v debug módu se zobrazí jako sekundární řádek
		// pokud se liší od primární zprávy (tzn. v normálním módu se neukáže)
		let serverMsgHtml = '';
		// if (this.options.debug && message.serverMessage && message.serverMessage !== message.message) {
		// 	serverMsgHtml = `<p style="font-size:.8rem;margin-top:4px;opacity:.7;font-style:italic">${this.escapeHtml(message.serverMessage)}</p>`;
		// }

		// Detail blok – viditelný ve verbose NEBO debug módu
		let detailHtml = '';
		if ((this.options.verbose || this.options.debug) && message.detail != null) {
			const raw = typeof message.detail === 'string'
				? message.detail
				: JSON.stringify(message.detail, null, 2);
			detailHtml = `<pre style="font-size:.75rem;margin-top:8px;padding:8px;background:rgba(0,0,0,.06);border-radius:4px;overflow:auto;max-height:200px;white-space:pre-wrap;word-break:break-word">${this.escapeHtml(raw)}</pre>`;
		}

		el.innerHTML = `
			<span class="${NOTIFICATION.ICON}">${LEVEL_ICONS[message.level]}</span>
			<div class="${NOTIFICATION.CONTENT}">
				<strong class="${NOTIFICATION.TITLE}">${title}</strong>
				<p class="${NOTIFICATION.MESSAGE}">${text}</p>
				${serverMsgHtml}
				${detailHtml}
			</div>
			<button class="${NOTIFICATION.CLOSE}" type="button" aria-label="Zavřít">${CLOSE_ICON}</button>
		`;

		// Close button handler
		const closeBtn = el.querySelector<HTMLElement>(`.${NOTIFICATION.CLOSE}`);
		closeBtn?.addEventListener('click', () => this.dismiss(el));

		return el;
	}

	private escapeHtml(str: string): string {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}
}
