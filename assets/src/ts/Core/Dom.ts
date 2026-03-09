import { assertDefined } from '@/Utils';

/**
 * Lightweight jQuery-like DOM utility.
 *
 * @example
 * const btn = Dom.el('button', { className: 'btn', text: 'OK' });
 * Dom.wrap(btn)
 *   .addClass('btn--primary')
 *   .attr('aria-pressed', 'false')
 *   .on('click', () => {});
 */
type EventMap = HTMLElementEventMap;
type AnyEl = HTMLElement;
type ElTag = keyof HTMLElementTagNameMap;

interface ElOptions {
    /** CSS class(es) – string nebo pole */
    className?: string | string[];
    /** id elementu */
    id?: string;
    /** textContent */
    text?: string;
    /** innerHTML (pozor na XSS – použijte Dom.esc()) */
    html?: string;
    /** HTML atributy */
    attr?: Record<string, string>;
    /** data-* atributy (bez prefixu data-) */
    data?: Record<string, string>;
    /** Inline styly */
    style?: Partial<CSSStyleDeclaration>;
    /** Potomci – elementy nebo stringy */
    children?: (Node | string)[];
    /** Event listenery */
    on?: Partial<{ [K in keyof EventMap]: (e: EventMap[K]) => void }>;
}

export class DomNode<T extends HTMLElement = HTMLElement> {
	readonly el: T;

    constructor(el: T) {
        this.el = el;
    }

	/**
	 * Getter/setter pro data-state atribut.
	 * Pokud je voláno bez argumentu, vrátí hodnotu data-state.
	 * Pokud je zadána hodnota, nastaví ji a vrátí instanci pro řetězení.
	 */
	state(): string | undefined;
	state(value: string): this;
	state(value?: string): string | this | undefined {
		if (value === undefined) return this.el.dataset.state;
		this.el.dataset.state = value;
		return this;
	}

	/**
	 * Přidá jednu nebo více CSS tříd k elementu.
	 * @param names Názvy tříd
	 * @returns Instanci pro řetězení
	 */
    addClass(...names: string[]): this {
        this.el.classList.add(...names);
        return this;
    }

	/**
	 * Odebere jednu nebo více CSS tříd z elementu.
	 * @param names Názvy tříd
	 * @returns Instanci pro řetězení
	 */
    removeClass(...names: string[]): this {
        this.el.classList.remove(...names);
        return this;
    }

	/**
	 * Přepne CSS třídu na elementu.
	 * @param name Název třídy
	 * @param force Volitelně vynutí přidání/odebrání
	 * @returns Instanci pro řetězení
	 */
    toggleClass(name: string, force?: boolean): this {
        this.el.classList.toggle(name, force);
        return this;
    }

	/**
	 * Zjistí, zda má element danou CSS třídu.
	 * @param name Název třídy
	 * @returns true pokud má třídu, jinak false
	 */
    hasClass(name: string): boolean {
        return this.el.classList.contains(name);
    }

	/**
	 * Getter/setter pro HTML atributy.
	 * - Bez argumentů vrátí objekt všech atributů elementu.
	 * - S jedním argumentem vrátí hodnotu daného atributu.
	 * - Se dvěma argumenty nastaví hodnotu atributu a vrátí instanci pro řetězení.
	 */
	attr(): Record<string, string>;
	attr(name: string): string | null;
	attr(name: string, value: string): this;
	attr(name?: string, value?: string): Record<string, string> | string | null | this {
		// vrátí všechny atributy
		if (name === undefined) {
			const attrs: Record<string, string> = {};
			for (const a of this.el.attributes) {
				attrs[a.name] = a.value;
			}
			return attrs;
		}

		// getter
		if (value === undefined) {
			return this.el.getAttribute(name);
		}

		// setter
		this.el.setAttribute(name, value);
		return this;
	}

	/**
	 * Odebere zadaný HTML atribut z elementu.
	 * @param name Název atributu
	 * @returns Instanci pro řetězení
	 */
    removeAttr(name: string): this {
        this.el.removeAttribute(name);
        return this;
    }

   /**
	 * Getter/setter pro data-* atributy přes dataset.
	 * - S jedním argumentem vrátí hodnotu data atributu.
	 * - Se dvěma argumenty nastaví hodnotu a vrátí instanci pro řetězení.
	 */
    data(key: string): string | undefined;
    data(key: string, value: string): this;
    data(key: string, value?: string): string | undefined | this {
        if (value === undefined) return this.el.dataset[key];
        this.el.dataset[key] = value;
        return this;
    }

	/**
	 * Odebere data-* atribut z elementu.
	 * @param key Název data atributu (bez prefixu "data-")
	 * @returns Instanci pro řetězení
	 */
    removeData(key: string): this {
        delete this.el.dataset[key];
        return this;
    }

	/**
	 * Getter/setter pro textový obsah elementu.
	 * - Bez argumentu vrátí textContent.
	 * - S argumentem nastaví textContent a vrátí instanci pro řetězení.
	 */
    text(): string;
    text(value: string): this;
    text(value?: string): string | this {
        if (value === undefined) return this.el.textContent ?? '';
        this.el.textContent = value;
        return this;
    }

	/**
	 * Getter/setter pro HTML obsah elementu.
	 * - Bez argumentu vrátí innerHTML.
	 * - S argumentem nastaví innerHTML a vrátí instanci pro řetězení.
	 */
    html(): string;
    html(value: string): this;
    html(value?: string): string | this {
        if (value === undefined) return this.el.innerHTML;
        this.el.innerHTML = value;
        return this;
    }

    /**
	 * Getter/setter pro value (input, select, textarea).
	 * - Bez argumentu vrátí value.
	 * - S argumentem nastaví value a vrátí instanci pro řetězení.
	 */
    val(): string;
    val(value: string): this;
    val(value?: string): string | this {
        const el = this.el as unknown as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
        if (value === undefined) return el.value;
        el.value = value;
        return this;
    }

	/**
	 * Getter/setter pro CSS styly.
	 * - css('color') vrátí hodnotu stylu.
	 * - css('color', 'red') nastaví styl.
	 * - css({ color: 'red', fontWeight: 'bold' }) nastaví více stylů najednou.
	 */
    css(prop: string): string;
    css(prop: string, value: string): this;
    css(props: Partial<CSSStyleDeclaration>): this;
    css(prop: string | Partial<CSSStyleDeclaration>, value?: string): string | this {
        if (typeof prop === 'string' && value === undefined) {
            return getComputedStyle(this.el).getPropertyValue(prop);
        }

        if (typeof prop === 'string') {
            this.el.style.setProperty(prop, value!);
        } else {
            Object.assign(this.el.style, prop);
        }

        return this;
    }

	/**
	 * Odebere jeden nebo více CSS stylů z elementu.
	 * Pokud je zadán řetězec, odebere danou vlastnost.
	 * Pokud je zadáno pole, odebere všechny uvedené vlastnosti.
	 *
	 * @param prop Název CSS vlastnosti nebo pole názvů
	 * @returns Instanci pro řetězení
	 */
	removeCss(prop: string | string[]): this {
		if (Array.isArray(prop)) {
			for (const p of prop) {
				this.el.style.removeProperty(p);
			}
		} else {
			this.el.style.removeProperty(prop);
		}

		return this;
	}

	/**
	 * Zobrazí element (hidden = false).
	 * @returns Instanci pro řetězení
	 */
    show(): this {
        this.el.hidden = false;
        return this;
    }

	/**
	 * Skryje element (hidden = true).
	 * @returns Instanci pro řetězení
	 */
    hide(): this {
        this.el.hidden = true;
        return this;
    }

	/**
	 * Přidá event listener na element.
	 * @param event Název události (např. 'click')
	 * @param handler Funkce, která se zavolá při události
	 * @param options Volitelné nastavení listeneru
	 * @returns Instanci pro řetězení
	 */
    on<K extends keyof EventMap>(event: K, handler: (e: EventMap[K]) => void, options?: AddEventListenerOptions): this {
        this.el.addEventListener(event, handler as EventListener, options);
        return this;
    }

	/**
	 * Odebere event listener z elementu.
	 * @param event Název události
	 * @param handler Funkce, která byla navěšena
	 * @returns Instanci pro řetězení
	 */
    off<K extends keyof EventMap>(event: K, handler: (e: EventMap[K]) => void): this {
        this.el.removeEventListener(event, handler as EventListener);
        return this;
    }

    /**
	 * Vyvolá nativní Event na elementu.
	 * @param event Název události
	 * @param options Volitelné parametry Eventu
	 * @returns Instanci pro řetězení
	 */
    trigger(event: string, options?: EventInit): this {
        this.el.dispatchEvent(new Event(event, { bubbles: true, ...options }));
        return this;
    }

   /**
	 * Vyvolá CustomEvent s daty na elementu.
	 * @param event Název události
	 * @param detail Data předaná v detailu eventu
	 * @returns Instanci pro řetězení
	 */
    triggerCustom<T = unknown>(event: string, detail?: T): this {
        this.el.dispatchEvent(new CustomEvent(event, { bubbles: true, detail }));
        return this;
    }

	/**
	 * Najde nejbližšího předka podle selektoru.
	 * @param selector CSS selektor
	 * @returns DomNode nebo null
	 */
    closest<K extends ElTag>(selector: K): DomNode | null;
    closest(selector: string): DomNode | null;
    closest(selector: string): DomNode | null {
        const found = this.el.closest<AnyEl>(selector);
        return found ? new DomNode(found) : null;
    }

	/**
	 * Vrátí rodičovský element jako DomNode, nebo null.
	 * @returns HtmlElement | null | DomNode
	 */
    parent(): DomNode | null {
        return this.el.parentElement ? new DomNode(this.el.parentElement) : null;
    }

	/**
	 * Najde první potomka podle selektoru.
	 * @param selector CSS selektor
	 * @returns DomNode nebo null
	 */
    find<K extends ElTag>(selector: K): DomNode | null;
    find(selector: string): DomNode | null;
    find(selector: string): DomNode | null {
        const found = this.el.querySelector<AnyEl>(selector);
        return found ? new DomNode(found) : null;
    }

	/**
	 * Najde všechny potomky podle selektoru a vrátí je jako DomList.
	 * @param selector CSS selektor
	 * @returns DomList s nalezenými elementy
	 */
	findAll<K extends ElTag>(selector: K): DomList<HTMLElementTagNameMap[K]>;
	findAll(selector: string): DomList;
	findAll(selector: string): any {
		const found = Array.from(this.el.querySelectorAll<AnyEl>(selector));
		return new DomList(found);
	}

	/**
	 * Zjistí, zda element obsahuje jiný node.
	 * @param other Node, který se má ověřit
	 * @returns true pokud je other potomkem, jinak false
	 */
    contains(other: Node): boolean {
        return this.el.contains(other);
    }

	/**
	 * Přidá jeden nebo více uzlů (Node, string nebo DomNode) na konec elementu.
	 * @param nodes Uzly nebo texty k přidání
	 * @returns Instanci pro řetězení
	 */
    append(...nodes: (Node | string | DomNode)[]): this {
        this.el.append(...nodes.map(n => n instanceof DomNode ? n.el : n));
        return this;
    }

	/**
	 * Přidá jeden nebo více uzlů (Node, string nebo DomNode) na začátek elementu.
	 * @param nodes Uzly nebo texty k přidání
	 * @returns Instanci pro řetězení
	 */
    prepend(...nodes: (Node | string | DomNode)[]): this {
        this.el.prepend(...nodes.map(n => n instanceof DomNode ? n.el : n));
        return this;
    }

	/**
	 * Vloží tento element před referenční element v DOMu.
	 * @param ref Element nebo DomNode, před který se má vložit
	 * @returns Instanci pro řetězení
	 */
    insertBefore(ref: AnyEl | DomNode): this {
        const refEl = ref instanceof DomNode ? ref.el : ref;
        refEl.parentNode?.insertBefore(this.el, refEl);
        return this;
    }

    /**
	 * Vloží tento element za referenční element v DOMu.
	 * @param ref Element nebo DomNode, za který se má vložit
	 * @returns Instanci pro řetězení
	 */
    insertAfter(ref: AnyEl | DomNode): this {
        const refEl = ref instanceof DomNode ? ref.el : ref;
        refEl.insertAdjacentElement('afterend', this.el);
        return this;
    }

	/**
	 * Odebere tento element z DOMu.
	 */
    remove(): void {
        this.el.remove();
    }

	/**
	 * Getter/setter pro vlastnosti DOM elementu (např. checked, value, disabled...).
	 * - Bez druhého argumentu vrátí hodnotu vlastnosti.
	 * - S druhým argumentem nastaví hodnotu vlastnosti a vrátí instanci pro řetězení.
	 * @param name Název vlastnosti (např. 'checked', 'value')
	 * @param value Nová hodnota vlastnosti (volitelné)
	 * @returns Hodnota vlastnosti nebo instance pro řetězení
	 */
    prop<K extends keyof T>(name: K): T[K];
    prop<K extends keyof T>(name: K, value: T[K]): this;
    prop<K extends keyof T>(name: K, value?: T[K]): T[K] | this {
        if (value === undefined) return this.el[name];
        (this.el as any)[name] = value;
        return this;
    }

	/**
	 * Nastaví fokus na element.
	 * @returns Instanci pro řetězení
	 */
    focus(): this {
        this.el.focus();
        return this;
    }

	/**
	 * Posune stránku tak, aby byl element viditelný v okně.
	 * @param opts Volitelné nastavení scrollování
	 * @returns Instanci pro řetězení
	 */
    scrollIntoView(opts?: ScrollIntoViewOptions): this {
        this.el.scrollIntoView(opts);
        return this;
    }
}

export class DomList<T extends HTMLElement = HTMLElement> {
	/** Pole obsahující všechny DomNode v kolekci (readonly). */
    readonly items: DomNode<T>[];

	/**
	 * Vytvoří novou kolekci DomNode z pole elementů.
	 * @param elements Pole HTML elementů
	 */
    constructor(elements: T[]) {
        this.items = elements.map(el => new DomNode(el));
    }

	/** Počet prvků v kolekci. */
    get length(): number {
        return this.items.length;
    }

   /**
	 * Provede funkci pro každý prvek v kolekci.
	 * @param fn Callback s DomNode a indexem
	 * @returns Instanci pro řetězení
	 */
	each(fn: (node: DomNode<T>, i: number) => void): this {
		this.items.forEach(fn);
		return this;
	}

    /**
	 * Vrátí DomNode na daném indexu, nebo undefined.
	 * @param index Index v kolekci
	 */
    at(index: number): DomNode | undefined {
        return this.items[index];
    }

    /**
	 * Vrátí novou kolekci s prvky, které splňují podmínku.
	 * @param fn Callback pro filtraci
	 * @returns Nová DomList s vyfiltrovanými prvky
	 */
    filter(fn: (node: DomNode, i: number) => boolean): DomList {
        const filtered = this.items.filter(fn);
        return new DomList(filtered.map(n => n.el));
    }

    /**
	 * Mapuje kolekci na nové pole hodnot.
	 * @param fn Callback pro mapování
	 * @returns Pole hodnot
	 */
    map<T>(fn: (node: DomNode, i: number) => T): T[] {
        return this.items.map(fn);
    }

	/** Přidá CSS třídy všem prvkům v kolekci. */
    addClass(...names: string[]): this {
        return this.each(n => n.addClass(...names));
    }

	/** Odebere CSS třídy všem prvkům v kolekci. */
    removeClass(...names: string[]): this {
        return this.each(n => n.removeClass(...names));
    }

	/** Přepne CSS třídu všem prvkům v kolekci. */
    toggleClass(name: string, force?: boolean): this {
        return this.each(n => n.toggleClass(name, force));
    }

	/** Nastaví HTML atribut všem prvkům v kolekci. */
    attr(name: string, value: string): this {
        return this.each(n => n.attr(name, value));
    }

	/** Odebere HTML atribut všem prvkům v kolekci. */
    removeAttr(name: string): this {
        return this.each(n => n.removeAttr(name));
    }

	/** Nastaví data-* atribut všem prvkům v kolekci. */
    data(key: string, value: string): this {
        return this.each(n => n.data(key, value));
    }

	/**
 	 * Nastaví CSS styly všem prvkům v kolekci.
	 * - css('color', 'red')
	 * - css({ color: 'red' })
	 */
    css(prop: string, value: string): this;
    css(props: Partial<CSSStyleDeclaration>): this;
    css(prop: string | Partial<CSSStyleDeclaration>, value?: string): this {
        return this.each(n => {
            if (typeof prop === 'string') n.css(prop, value!);
            else n.css(prop);
        });
    }

	/** Nastaví textový obsah všem prvkům v kolekci. */
    text(value: string): this {
        return this.each(n => n.text(value));
    }

	/** Nastaví HTML obsah všem prvkům v kolekci. */
    html(value: string): this {
        return this.each(n => n.html(value));
    }

	/** Nastaví value všem prvkům v kolekci (input, select, textarea). */
    val(value: string): this {
        return this.each(n => n.val(value));
    }

	/** Přidá event listener všem prvkům v kolekci. */
    on<K extends keyof EventMap>(event: K, handler: (e: EventMap[K]) => void, options?: AddEventListenerOptions): this {
        return this.each(n => n.on(event, handler, options));
    }

	/** Odebere event listener všem prvkům v kolekci. */
    off<K extends keyof EventMap>(event: K, handler: (e: EventMap[K]) => void): this {
        return this.each(n => n.off(event, handler));
    }

	/** Zobrazí všechny prvky v kolekci. */
    show(): this {
        return this.each(n => n.show());
    }

	/** Skryje všechny prvky v kolekci. */
    hide(): this {
        return this.each(n => n.hide());
    }

	/** Odebere všechny prvky v kolekci z DOMu. */
    remove(): void {
        this.items.forEach(n => n.remove());
    }
}

export class Dom {
    /**
	 * Vybere první element podle CSS selektoru v rámci zadaného rodiče (nebo v celém dokumentu).
	 * @param selector CSS selektor
	 * @param parent Nadřazený element nebo document (výchozí: document)
	 * @returns První nalezený element nebo null
	 */
    static q<K extends ElTag>(selector: K, parent?: AnyEl | Document): HTMLElementTagNameMap[K] | null;
    static q(selector: string, parent?: AnyEl | Document): AnyEl | null;
    static q(selector: string, parent: AnyEl | Document = document): AnyEl | null {
        return parent.querySelector<AnyEl>(selector);
    }

	/**
	 * Vybere první element podle selektoru a pokud neexistuje, vyhodí chybu.
	 * @param selector CSS selektor
	 * @param parent Nadřazený element nebo document (volitelné)
	 * @returns První nalezený element (nikdy null)
	 * @throws Pokud element neexistuje
	 */
	static qRequired<T extends HTMLElement>(selector: string, parent?: AnyEl | Document): T {
		const el = Dom.q(selector, parent);
		assertDefined(el, `Element not found: ${selector}`);
		return el as T;
	}

   /**
	 * Vybere všechny elementy podle CSS selektoru v rámci zadaného rodiče (nebo v celém dokumentu).
	 * @param selector CSS selektor
	 * @param parent Nadřazený element nebo document (výchozí: document)
	 * @returns Pole nalezených elementů
	 */
    static qa<K extends ElTag>(selector: K, parent?: AnyEl | Document): HTMLElementTagNameMap[K][];
    static qa(selector: string, parent?: AnyEl | Document): AnyEl[];
    static qa(selector: string, parent: AnyEl | Document = document): AnyEl[] {
        return Array.from(parent.querySelectorAll<AnyEl>(selector));
    }

	/**
	 * Najde první element podle zadaného data-* atributu a hodnoty.
	 * Pokud element neexistuje, vyhodí chybu.
	 *
	 * @param attr Název data atributu (bez prefixu "data-")
	 * @param value Hodnota atributu, kterou hledáme
	 * @param parent Nadřazený element nebo document (výchozí: document)
	 * @returns První nalezený element (nikdy null)
	 * @throws Pokud element neexistuje
	 */
	static byData(attr: string, value: string|number, parent: AnyEl | Document = document): AnyEl {
		const el = parent.querySelector(`[data-${attr}="${value}"]`);
		assertDefined(el, `Element not found: data-${attr}="${value}"`);
		return el as AnyEl;
	}

	/**
	 * Najde element podle data-flag atributu a vrátí ho jako DomNode.
	 * @param name Hodnota atributu data-flag
	 * @param parent Nadřazený element, ve kterém hledat (výchozí: document)
	 * @returns DomNode nebo null, pokud nenalezeno
	 */
	static flag(name: string, parent: AnyEl | Document = document): DomNode {
  		return new DomNode(this.byData("flag", name, parent));
	}

	/**
	 * Najde element podle data-action atributu a vrátí ho jako DomNode.
	 * @param name Hodnota atributu data-action
	 * @param parent Nadřazený element, ve kterém hledat (výchozí: document)
	 * @returns DomNode nebo null, pokud nenalezeno
	 */
	static action(name: string, parent: AnyEl | Document = document): DomNode {
  		return new DomNode(this.byData("action", name, parent));
	}

	/**
	 * Najde element podle data-component atributu a vrátí ho jako DomNode.
	 * @param name Hodnota atributu data-component
	 * @param parent Nadřazený element, ve kterém hledat (výchozí: document)
	 * @returns DomNode nebo null, pokud nenalezeno
	 */
	static component(name: string, parent?: AnyEl | Document) {
		return new DomNode(this.byData("component", name, parent));
	}

    /**
	 * Obalí jeden HTML element do řetězitelné DomNode.
	 * Umožňuje používat jQuery-like API na jednom elementu.
	 * @param el HTML element k obalení
	 * @returns Nová instance DomNode
	 */
    static wrap(el: AnyEl): DomNode {
        return new DomNode(el);
    }

	/**
	 * Obalí kolekci elementů nebo selektor do DomList (batch API).
	 * Pokud je zadán selektor (string), vybere všechny odpovídající elementy v rámci parent.
	 * Pokud je zadáno pole elementů, obalí je přímo.
	 * @param input CSS selektor nebo pole elementů
	 * @param parent Nadřazený element pro selektor (volitelné)
	 * @returns Nová instance DomList
	 */
    static wrapAll(selector: string, parent?: AnyEl | Document): DomList;
    static wrapAll(elements: AnyEl[]): DomList;
    static wrapAll(input: string | AnyEl[], parent?: AnyEl | Document): DomList {
        const els = typeof input === 'string'
            ? Dom.qa(input, parent ?? document)
            : input;
        return new DomList(els);
    }

   /**
	 * Vytvoří nový HTML element s volitelnou konfigurací.
	 * @param tag Název tagu (např. 'div', 'button', ...)
	 * @param opts Volitelné nastavení elementu (třídy, id, text, atributy, data, styl, děti, eventy)
	 * @returns Nově vytvořený element
	 */
	static el<K extends ElTag>(tag: K, opts?: ElOptions): HTMLElementTagNameMap[K] {
		const element = document.createElement(tag);

		if (!opts) return element;

		// Nastaví CSS třídy
		if (opts.className) {
			const classes = Array.isArray(opts.className) ? opts.className : opts.className.split(/\s+/);
			element.classList.add(...classes.filter(Boolean));
		}

		// Nastaví id
		if (opts.id) element.id = opts.id;

		// Nastaví textContent nebo innerHTML
		if (opts.text !== undefined) element.textContent = opts.text;
		else if (opts.html !== undefined) element.innerHTML = opts.html;

		// Nastaví HTML atributy
		if (opts.attr) {
			for (const [k, v] of Object.entries(opts.attr)) {
				element.setAttribute(k, v);
			}
		}

		// Nastaví data-* atributy
		if (opts.data) {
			for (const [k, v] of Object.entries(opts.data)) {
				element.dataset[k] = v;
			}
		}

		// Nastaví inline styly
		if (opts.style) {
			Object.assign(element.style, opts.style);
		}

		// Přidá potomky (children)
		if (opts.children) {
			element.append(...opts.children);
		}

		// Navěsí event listenery
		if (opts.on) {
			for (const [ev, handler] of Object.entries(opts.on)) {
				element.addEventListener(ev, handler as EventListener);
			}
		}

		return element;
	}

	/**
	 * Vytvoří HTML element a rovnou ho obalí do DomNode (řetězitelné API).
	 * Umožňuje okamžitě používat jQuery-like metody na novém elementu.
	 * @param tag Název tagu (např. 'div', 'button', ...)
	 * @param opts Volitelné nastavení elementu (třídy, atributy, obsah, eventy...)
	 * @returns Nová instance DomNode s vytvořeným elementem
	 */
    static create<K extends ElTag>(tag: K, opts?: ElOptions): DomNode<HTMLElementTagNameMap[K]> {
        return new DomNode(Dom.el(tag, opts));
    }

    /**
	 * Bezpečně escapuje HTML znaky ve stringu (prevence XSS).
	 * @param str Vstupní text
	 * @returns Escapovaný HTML řetězec
	 */
    static esc(str: string): string {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

	/**
	 * Zkratka pro DOMContentLoaded – spustí funkci po načtení DOMu.
	 * @param fn Funkce, která se má spustit po načtení stránky
	 */
    static ready(fn: () => void): void {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

	/**
	 * Deleguje události z dokumentu na potomky podle selektoru.
	 * Umožňuje efektivně navěsit jeden listener na celý dokument,
	 * který reaguje pouze na elementy odpovídající zadanému selektoru.
	 *
	 * @param event Název události (např. 'click', 'input', ...)
	 * @param selector CSS selektor cílových elementů
	 * @param handler Funkce, která se zavolá při události na odpovídajícím elementu
	 */
	static delegate<K extends keyof DocumentEventMap>(event: K, selector: string, handler: (el: HTMLElement, e: DocumentEventMap[K]) => void): void {
		document.addEventListener(event, (e) => {
			const target = e.target as HTMLElement | null;
			if (!target) return;

			const el = target.closest(selector);
			if (!el) return;

			handler(el as HTMLElement, e);
		});
	}

	// Dom.delegateWithin(container, 'click', '[data-action]', handler)
}