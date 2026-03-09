import { FLAGS } from '@/Const';
import { EventBus, Dom, DomNode } from '@/Core';
import { ApiClient, SessionManager } from '@/Services';

/**
 * ResultActionHandler - Obsluha akcí ve výsledcích AI generování
 *
 * Zpracovává kliknutí na akční tlačítka (repeat, copy, use, thumb-up/down)
 * ve výsledcích generování. Využívá data-atributy na tlačítkách
 * pro identifikaci klíče a indexu výsledku.
 *
 * Spolupracuje se SessionManager pro regeneraci jednoho klíče:
 * - Při "repeat" vezme data z relace + přidá __generate_key
 * - Odešle request přes ApiClient
 * - Aktualizuje pouze daný element ve výsledcích
 */

export interface ResultAction {
	action: string;
	key: string;
	index: number;
}

export class ResultActionHandler {
	constructor(
		private readonly events: EventBus,
		private readonly api: ApiClient,
		private readonly session: SessionManager,
	) {}

	/**
	 * Inicializuje delegovaný event listener na result kontejneru.
	 * Používá event delegation — stačí navěsit jednou,
	 * funguje i pro dynamicky přidané výsledky.
	 */
	init(): void {
		Dom.delegate('click', '[data-action]', (el, e) => {
			e.preventDefault();

			const button = Dom.wrap(el);
			const parsed = this.parseAction(button);

			if (!parsed) return;

			this.handleAction(parsed, button);
		});
	}

	/**
	 * Parsuje akci z data-atributů tlačítka výsledku.
	 * Získá typ akce (např. "repeat", "copy"), klíč a index výsledku z atributů.
	 *
	 * @param button DomNode tlačítka, na kterém byla akce vyvolána
	 * @returns Objekt ResultAction s typem akce, klíčem a indexem, nebo null pokud chybí data
	 */
	private parseAction(button: DomNode<HTMLElement>): ResultAction | null {
		const action = button.attr('data-action');
		const flags = button.attr('role');

		if (!action || !flags) return null;

		const parts = flags.split(' ');
		const key = parts[0] ?? '';
		const index = parseInt(parts[1] ?? '0', 10);

		return { action, key, index };
	}

	/**
	 * Zpracuje akci podle typu na základě hodnoty action.action.
	 * Podle typu akce zavolá odpovídající metodu nebo vypíše informaci do konzole.
	 *
	 * @param action Objekt s informacemi o akci (typ, klíč, index)
	 * @param button DomNode tlačítka, na kterém byla akce vyvolána
	 */
	private handleAction(action: ResultAction, button: DomNode<HTMLElement>): void {
		switch (action.action) {
			case 'repeat':
				this.handleRepeat(action, button as DomNode<HTMLButtonElement>);
				break;
			case 'copy':
				this.handleCopy(button);
				break;
			case 'use':
				console.log("Bude v budoucnu");
				// this.handleUse(action, button);
				break;
			case 'thumb-up':
			case 'thumb-down':
				console.log("Bude v budoucnu");
				// this.handleFeedback(action, button);
				break;
		}
	}

	/**
	 * Zajistí regeneraci jednoho klíče (repeat) ve výsledcích.
	 * 1. Zkontroluje, zda existuje aktivní relace (session).
	 * 2. Najde wrapper element podle [data-key].
	 * 3. Sestaví payload pro API (přidá __generate_key).
	 * 4. Najde obsahový element pro výsledek.
	 * 5. Deaktivuje tlačítko po dobu požadavku.
	 * 6. Odešle požadavek na API a aktualizuje obsah výsledku.
	 * 7. Pošle event o regeneraci.
	 * 8. Zajistí minimální dobu zobrazení loaderu.
	 * 9. Opět aktivuje tlačítko.
	 *
	 * @param action Objekt s informacemi o akci (typ, klíč, index)
	 * @param button DomNode tlačítka, na kterém byla akce vyvolána
	 */
	private async handleRepeat(action: ResultAction, button: DomNode<HTMLButtonElement>): Promise<void> {
		if (!this.session.hasSession()) {
			console.warn('[ResultAction] Žádná aktivní relace pro regeneraci.');
			return;
		}

		// Najde wrapper element podle data-key (výsledek, který se má aktualizovat)
		const wrapper = button.closest('[data-key]');
		if (!wrapper) return;

		 // Sestaví payload pro API (včetně __generate_key)
		const payload = this.session.buildSingleKeyPayload(action.key);
		if (!payload) return;

		// Najde obsahový element výsledku (kam se vloží nový HTML obsah)
		const content = Dom.flag(FLAGS.RESULT_CONTENT, wrapper.el);

		button.prop('disabled', true);

		const MIN_LOADER_TIME = 300;
		const startTime = performance.now();

		try {
			const result = await this.api.via('http').send(payload);

			if (result?.mode === 'http') {
				content.html(result.response.data.html);
			}

			this.events.publish('regenerate-key', {
				key: action.key,
				index: action.index,
				data: result ?? null,
			});

		} catch (error) {
			console.error('[ResultAction] Chyba regenerace:', error);
		} finally {
			const elapsed = performance.now() - startTime;
			const remaining = MIN_LOADER_TIME - elapsed;

			if (remaining > 0) {
				await new Promise(resolve => setTimeout(resolve, remaining));
			}

			button.prop('disabled', false);
		}
	}

	/**
	 * Zkopíruje obsah výsledku do schránky.
	 *
	 * @param button DomNode tlačítka, na kterém byla akce vyvolána
	 */
	private async handleCopy(button: DomNode<HTMLElement>): Promise<void> {
		const wrapper = button.closest('[data-key]');
		if (!wrapper) return;

		const content = Dom.flag("result-content", wrapper.el);
		const text = content.text().trim();

		if (!text) return;

		try {
			await navigator.clipboard.writeText(text);
			this.events.publish('copy', { success: true });
		} catch (error) {
			console.error('[ResultAction] Kopírování selhalo:', error);
			this.events.publish('copy', { success: false });
		}
	}

	// /**
	//  * "Použít" — emituje event s klíčem a hodnotou pro cílovou aplikaci.
	//  */
	// private handleUse(action: ResultAction, button: HTMLElement): void {
	// 	const wrapper = button.closest<HTMLElement>('.ai-result__wrapper');
	// 	if (!wrapper) return;

	// 	const contentEl = wrapper.querySelector<HTMLElement>('.ai-result__content');
	// 	if (!contentEl) return;

	// 	const text = contentEl.textContent?.trim() ?? '';

	// 	this.events.emit('use', {
	// 		data: {
	// 			[action.key]: text,
	// 		},
	// 	});

	// 	// Vizuální feedback
	// 	const item = wrapper.closest<HTMLElement>('.ai-result__item');
	// 	item?.classList.add('is-used');
	// }

	// /**
	//  * Feedback (thumb up/down) — emituje event.
	//  */
	// private handleFeedback(action: ResultAction, button: HTMLElement): void {
	// 	button.classList.toggle('is-active');

	// 	// Deaktivuj opačné tlačítko
	// 	const wrapper = button.closest<HTMLElement>('.ai-result__wrapper');
	// 	if (wrapper) {
	// 		const opposite = action.action === 'thumb-up' ? 'thumb-down' : 'thumb-up';
	// 		const oppositeBtn = wrapper.querySelector<HTMLElement>(`[action="${opposite}"]`);
	// 		oppositeBtn?.classList.remove('is-active');
	// 	}
	// }
}