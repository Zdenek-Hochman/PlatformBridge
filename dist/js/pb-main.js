(() => {
  // assets/ts/Const/Components.ts
  var MODULE = {
    ROOT: "pb-module",
    TITLE: "pb-module__title",
    FORM: "pb-module__form",
    WRAPPER: "pb-module__wrapper",
    SECTION: "pb-module__section",
    SECTION_GRID: "pb-module__section--grid",
    BLOCK: "pb-module__block",
    BLOCK_HIDDEN: "pb-module__block--hidden",
    RESULT: "pb-module__result",
    RESULT_TITLE: "pb-module__result-title",
    FIELD: "pb-module__field",
    FIELD_ERROR: "pb-module__field--error",
    INPUT: "pb-module__input",
    TEXTAREA: "pb-module__textarea",
    SELECT: "pb-module__select",
    LABEL: "pb-module__label",
    LABEL_VALUE: "pb-module__label-value",
    SMALL: "pb-module__small",
    TICK_BOX: "pb-module__tick-box",
    BUTTON: "pb-module__button",
    BUTTON_PRIMARY: "pb-module__button--primary",
    ERROR_MSG: "pb-module__error",
    OVERLAY: "pb-module__overlay",
    LOADING: "pb-module--loading",
    SUBMIT_LOADING: "pb-module__submit--loading"
  };
  var SELECT = {
    ROOT: "pb-select",
    NATIVE: "pb-select__native",
    TRIGGER: "pb-select__trigger",
    DROPDOWN: "pb-select__dropdown",
    OPTION: "pb-select__option",
    OPTION_SELECTED: "pb-select__option--selected",
    OPTION_FOCUSED: "pb-select__option--focused",
    OPTION_DISABLED: "pb-select__option--disabled",
    OPEN: "pb-select--open",
    DISABLED: "pb-select--disabled"
  };
  var NOTIFICATION = {
    ROOT: "pb-notification",
    CONTAINER: "pb-notifications",
    ICON: "pb-notification__icon",
    CONTENT: "pb-notification__content",
    TITLE: "pb-notification__title",
    MESSAGE: "pb-notification__message",
    CLOSE: "pb-notification__close",
    ERROR: "pb-notification--error",
    SUCCESS: "pb-notification--success",
    WARNING: "pb-notification--warning",
    INFO: "pb-notification--info",
    LEAVING: "pb-notification--leaving"
  };
  var COMPONENTS = {
    RESULT_CONTAINER: "pb-result",
    HANDLERS: "pb-handlers"
  };
  var FLAGS = {
    RESULTS: "results",
    RESULT_CONTENT: "result-content"
  };

  // assets/ts/Utils/Assert.ts
  function assertDefined(value, message = "Value is undefined") {
    if (value == null) {
      throw new Error(message);
    }
  }

  // assets/ts/Core/Dom.ts
  var DomNode = class _DomNode {
    el;
    constructor(el) {
      this.el = el;
    }
    state(value) {
      if (value === void 0) return this.el.dataset.state;
      this.el.dataset.state = value;
      return this;
    }
    /**
     * Přidá jednu nebo více CSS tříd k elementu.
     * @param names Názvy tříd
     * @returns Instanci pro řetězení
     */
    addClass(...names) {
      this.el.classList.add(...names);
      return this;
    }
    /**
     * Odebere jednu nebo více CSS tříd z elementu.
     * @param names Názvy tříd
     * @returns Instanci pro řetězení
     */
    removeClass(...names) {
      this.el.classList.remove(...names);
      return this;
    }
    /**
     * Přepne CSS třídu na elementu.
     * @param name Název třídy
     * @param force Volitelně vynutí přidání/odebrání
     * @returns Instanci pro řetězení
     */
    toggleClass(name, force) {
      this.el.classList.toggle(name, force);
      return this;
    }
    /**
     * Zjistí, zda má element danou CSS třídu.
     * @param name Název třídy
     * @returns true pokud má třídu, jinak false
     */
    hasClass(name) {
      return this.el.classList.contains(name);
    }
    attr(name, value) {
      if (name === void 0) {
        const attrs = {};
        for (const a of this.el.attributes) {
          attrs[a.name] = a.value;
        }
        return attrs;
      }
      if (value === void 0) {
        return this.el.getAttribute(name);
      }
      this.el.setAttribute(name, value);
      return this;
    }
    /**
     * Odebere zadaný HTML atribut z elementu.
     * @param name Název atributu
     * @returns Instanci pro řetězení
     */
    removeAttr(name) {
      this.el.removeAttribute(name);
      return this;
    }
    data(key, value) {
      if (value === void 0) return this.el.dataset[key];
      this.el.dataset[key] = value;
      return this;
    }
    /**
     * Odebere data-* atribut z elementu.
     * @param key Název data atributu (bez prefixu "data-")
     * @returns Instanci pro řetězení
     */
    removeData(key) {
      delete this.el.dataset[key];
      return this;
    }
    text(value) {
      if (value === void 0) return this.el.textContent ?? "";
      this.el.textContent = value;
      return this;
    }
    html(value) {
      if (value === void 0) return this.el.innerHTML;
      this.el.innerHTML = value;
      return this;
    }
    val(value) {
      const el = this.el;
      if (value === void 0) return el.value;
      el.value = value;
      return this;
    }
    css(prop, value) {
      if (typeof prop === "string" && value === void 0) {
        return getComputedStyle(this.el).getPropertyValue(prop);
      }
      if (typeof prop === "string") {
        this.el.style.setProperty(prop, value);
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
    removeCss(prop) {
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
    show() {
      this.el.hidden = false;
      return this;
    }
    /**
     * Skryje element (hidden = true).
     * @returns Instanci pro řetězení
     */
    hide() {
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
    on(event, handler, options) {
      this.el.addEventListener(event, handler, options);
      return this;
    }
    /**
     * Odebere event listener z elementu.
     * @param event Název události
     * @param handler Funkce, která byla navěšena
     * @returns Instanci pro řetězení
     */
    off(event, handler) {
      this.el.removeEventListener(event, handler);
      return this;
    }
    /**
    * Vyvolá nativní Event na elementu.
    * @param event Název události
    * @param options Volitelné parametry Eventu
    * @returns Instanci pro řetězení
    */
    trigger(event, options) {
      this.el.dispatchEvent(new Event(event, { bubbles: true, ...options }));
      return this;
    }
    /**
    * Vyvolá CustomEvent s daty na elementu.
    * @param event Název události
    * @param detail Data předaná v detailu eventu
    * @returns Instanci pro řetězení
    */
    triggerCustom(event, detail) {
      this.el.dispatchEvent(new CustomEvent(event, { bubbles: true, detail }));
      return this;
    }
    closest(selector) {
      const found = this.el.closest(selector);
      return found ? new _DomNode(found) : null;
    }
    /**
     * Vrátí rodičovský element jako DomNode, nebo null.
     * @returns HtmlElement | null | DomNode
     */
    parent() {
      return this.el.parentElement ? new _DomNode(this.el.parentElement) : null;
    }
    find(selector) {
      const found = this.el.querySelector(selector);
      return found ? new _DomNode(found) : null;
    }
    findAll(selector) {
      const found = Array.from(this.el.querySelectorAll(selector));
      return new DomList(found);
    }
    /**
     * Zjistí, zda element obsahuje jiný node.
     * @param other Node, který se má ověřit
     * @returns true pokud je other potomkem, jinak false
     */
    contains(other) {
      return this.el.contains(other);
    }
    /**
     * Přidá jeden nebo více uzlů (Node, string nebo DomNode) na konec elementu.
     * @param nodes Uzly nebo texty k přidání
     * @returns Instanci pro řetězení
     */
    append(...nodes) {
      this.el.append(...nodes.map((n) => n instanceof _DomNode ? n.el : n));
      return this;
    }
    /**
     * Přidá jeden nebo více uzlů (Node, string nebo DomNode) na začátek elementu.
     * @param nodes Uzly nebo texty k přidání
     * @returns Instanci pro řetězení
     */
    prepend(...nodes) {
      this.el.prepend(...nodes.map((n) => n instanceof _DomNode ? n.el : n));
      return this;
    }
    /**
     * Vloží tento element před referenční element v DOMu.
     * @param ref Element nebo DomNode, před který se má vložit
     * @returns Instanci pro řetězení
     */
    insertBefore(ref) {
      const refEl = ref instanceof _DomNode ? ref.el : ref;
      refEl.parentNode?.insertBefore(this.el, refEl);
      return this;
    }
    /**
    * Vloží tento element za referenční element v DOMu.
    * @param ref Element nebo DomNode, za který se má vložit
    * @returns Instanci pro řetězení
    */
    insertAfter(ref) {
      const refEl = ref instanceof _DomNode ? ref.el : ref;
      refEl.insertAdjacentElement("afterend", this.el);
      return this;
    }
    /**
     * Odebere tento element z DOMu.
     */
    remove() {
      this.el.remove();
    }
    prop(name, value) {
      if (value === void 0) return this.el[name];
      this.el[name] = value;
      return this;
    }
    /**
     * Nastaví fokus na element.
     * @returns Instanci pro řetězení
     */
    focus() {
      this.el.focus();
      return this;
    }
    /**
     * Posune stránku tak, aby byl element viditelný v okně.
     * @param opts Volitelné nastavení scrollování
     * @returns Instanci pro řetězení
     */
    scrollIntoView(opts) {
      this.el.scrollIntoView(opts);
      return this;
    }
  };
  var DomList = class _DomList {
    /** Pole obsahující všechny DomNode v kolekci (readonly). */
    items;
    /**
     * Vytvoří novou kolekci DomNode z pole elementů.
     * @param elements Pole HTML elementů
     */
    constructor(elements) {
      this.items = elements.map((el) => new DomNode(el));
    }
    /** Počet prvků v kolekci. */
    get length() {
      return this.items.length;
    }
    /**
    * Provede funkci pro každý prvek v kolekci.
    * @param fn Callback s DomNode a indexem
    * @returns Instanci pro řetězení
    */
    each(fn) {
      this.items.forEach(fn);
      return this;
    }
    /**
    * Vrátí DomNode na daném indexu, nebo undefined.
    * @param index Index v kolekci
    */
    at(index) {
      return this.items[index];
    }
    /**
    * Vrátí novou kolekci s prvky, které splňují podmínku.
    * @param fn Callback pro filtraci
    * @returns Nová DomList s vyfiltrovanými prvky
    */
    filter(fn) {
      const filtered = this.items.filter(fn);
      return new _DomList(filtered.map((n) => n.el));
    }
    /**
    * Mapuje kolekci na nové pole hodnot.
    * @param fn Callback pro mapování
    * @returns Pole hodnot
    */
    map(fn) {
      return this.items.map(fn);
    }
    /** Přidá CSS třídy všem prvkům v kolekci. */
    addClass(...names) {
      return this.each((n) => n.addClass(...names));
    }
    /** Odebere CSS třídy všem prvkům v kolekci. */
    removeClass(...names) {
      return this.each((n) => n.removeClass(...names));
    }
    /** Přepne CSS třídu všem prvkům v kolekci. */
    toggleClass(name, force) {
      return this.each((n) => n.toggleClass(name, force));
    }
    /** Nastaví HTML atribut všem prvkům v kolekci. */
    attr(name, value) {
      return this.each((n) => n.attr(name, value));
    }
    /** Odebere HTML atribut všem prvkům v kolekci. */
    removeAttr(name) {
      return this.each((n) => n.removeAttr(name));
    }
    /** Nastaví data-* atribut všem prvkům v kolekci. */
    data(key, value) {
      return this.each((n) => n.data(key, value));
    }
    css(prop, value) {
      return this.each((n) => {
        if (typeof prop === "string") n.css(prop, value);
        else n.css(prop);
      });
    }
    /** Nastaví textový obsah všem prvkům v kolekci. */
    text(value) {
      return this.each((n) => n.text(value));
    }
    /** Nastaví HTML obsah všem prvkům v kolekci. */
    html(value) {
      return this.each((n) => n.html(value));
    }
    /** Nastaví value všem prvkům v kolekci (input, select, textarea). */
    val(value) {
      return this.each((n) => n.val(value));
    }
    /** Přidá event listener všem prvkům v kolekci. */
    on(event, handler, options) {
      return this.each((n) => n.on(event, handler, options));
    }
    /** Odebere event listener všem prvkům v kolekci. */
    off(event, handler) {
      return this.each((n) => n.off(event, handler));
    }
    /** Zobrazí všechny prvky v kolekci. */
    show() {
      return this.each((n) => n.show());
    }
    /** Skryje všechny prvky v kolekci. */
    hide() {
      return this.each((n) => n.hide());
    }
    /** Odebere všechny prvky v kolekci z DOMu. */
    remove() {
      this.items.forEach((n) => n.remove());
    }
  };
  var Dom = class _Dom {
    static q(selector, parent = document) {
      return parent.querySelector(selector);
    }
    /**
     * Vybere první element podle selektoru a pokud neexistuje, vyhodí chybu.
     * @param selector CSS selektor
     * @param parent Nadřazený element nebo document (volitelné)
     * @returns První nalezený element (nikdy null)
     * @throws Pokud element neexistuje
     */
    static qRequired(selector, parent) {
      const el = _Dom.q(selector, parent);
      assertDefined(el, `Element not found: ${selector}`);
      return el;
    }
    static qa(selector, parent = document) {
      return Array.from(parent.querySelectorAll(selector));
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
    static byData(attr, value, parent = document) {
      const el = parent.querySelector(`[data-${attr}="${value}"]`);
      assertDefined(el, `Element not found: data-${attr}="${value}"`);
      return el;
    }
    /**
     * Najde element podle data-flag atributu a vrátí ho jako DomNode.
     * @param name Hodnota atributu data-flag
     * @param parent Nadřazený element, ve kterém hledat (výchozí: document)
     * @returns DomNode nebo null, pokud nenalezeno
     */
    static flag(name, parent = document) {
      return new DomNode(this.byData("flag", name, parent));
    }
    /**
     * Najde element podle data-action atributu a vrátí ho jako DomNode.
     * @param name Hodnota atributu data-action
     * @param parent Nadřazený element, ve kterém hledat (výchozí: document)
     * @returns DomNode nebo null, pokud nenalezeno
     */
    static action(name, parent = document) {
      return new DomNode(this.byData("action", name, parent));
    }
    /**
     * Najde element podle data-component atributu a vrátí ho jako DomNode.
     * @param name Hodnota atributu data-component
     * @param parent Nadřazený element, ve kterém hledat (výchozí: document)
     * @returns DomNode nebo null, pokud nenalezeno
     */
    static component(name, parent) {
      return new DomNode(this.byData("component", name, parent));
    }
    /**
    * Obalí jeden HTML element do řetězitelné DomNode.
    * Umožňuje používat jQuery-like API na jednom elementu.
    * @param el HTML element k obalení
    * @returns Nová instance DomNode
    */
    static wrap(el) {
      return new DomNode(el);
    }
    static wrapAll(input, parent) {
      const els = typeof input === "string" ? _Dom.qa(input, parent ?? document) : input;
      return new DomList(els);
    }
    /**
    * Vytvoří nový HTML element s volitelnou konfigurací.
    * @param tag Název tagu (např. 'div', 'button', ...)
    * @param opts Volitelné nastavení elementu (třídy, id, text, atributy, data, styl, děti, eventy)
    * @returns Nově vytvořený element
    */
    static el(tag, opts) {
      const element = document.createElement(tag);
      if (!opts) return element;
      if (opts.className) {
        const classes = Array.isArray(opts.className) ? opts.className : opts.className.split(/\s+/);
        element.classList.add(...classes.filter(Boolean));
      }
      if (opts.id) element.id = opts.id;
      if (opts.text !== void 0) element.textContent = opts.text;
      else if (opts.html !== void 0) element.innerHTML = opts.html;
      if (opts.attr) {
        for (const [k, v] of Object.entries(opts.attr)) {
          element.setAttribute(k, v);
        }
      }
      if (opts.data) {
        for (const [k, v] of Object.entries(opts.data)) {
          element.dataset[k] = v;
        }
      }
      if (opts.style) {
        Object.assign(element.style, opts.style);
      }
      if (opts.children) {
        element.append(...opts.children);
      }
      if (opts.on) {
        for (const [ev, handler] of Object.entries(opts.on)) {
          element.addEventListener(ev, handler);
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
    static create(tag, opts) {
      return new DomNode(_Dom.el(tag, opts));
    }
    /**
    * Bezpečně escapuje HTML znaky ve stringu (prevence XSS).
    * @param str Vstupní text
    * @returns Escapovaný HTML řetězec
    */
    static esc(str) {
      const div = document.createElement("div");
      div.textContent = str;
      return div.innerHTML;
    }
    /**
     * Zkratka pro DOMContentLoaded – spustí funkci po načtení DOMu.
     * @param fn Funkce, která se má spustit po načtení stránky
     */
    static ready(fn) {
      if (document.readyState !== "loading") fn();
      else document.addEventListener("DOMContentLoaded", fn);
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
    static delegate(event, selector, handler) {
      document.addEventListener(event, (e) => {
        const target = e.target;
        if (!target) return;
        const el = target.closest(selector);
        if (!el) return;
        handler(el, e);
      });
    }
    // Dom.delegateWithin(container, 'click', '[data-action]', handler)
  };

  // assets/ts/Core/EventBus.ts
  var EventBus = class {
    /** Interní listenery */
    listeners = /* @__PURE__ */ new Map();
    /** DOM element, na který se dispatchují CustomEventy */
    target;
    /** Prefix pro DOM eventy */
    DOM_PREFIX = "pb";
    constructor(target = window) {
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
    subscribe(event, callback) {
      if (!this.listeners.has(event)) {
        this.listeners.set(event, /* @__PURE__ */ new Set());
      }
      this.listeners.get(event).add(callback);
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
    unsubscribe(event, callback) {
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
    once(event, callback) {
      const wrapper = (payload) => {
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
    publish(event, payload) {
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
      const domEvent = new CustomEvent(`${this.DOM_PREFIX}:${event}`, {
        detail: payload,
        bubbles: true,
        cancelable: false
      });
      this.target.dispatchEvent(domEvent);
    }
    /**
     * Odstraní všechny listenery pro všechny eventy.
     *
     * Po zavolání této metody nebude na žádný event zaregistrován žádný interní listener.
     */
    clear() {
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
    clearTopic(event) {
      this.listeners.delete(event);
    }
    /** @alias publish */
    /** @deprecated */
    emit(event, payload) {
      this.publish(event, payload);
    }
    /** @alias subscribe */
    /** @deprecated */
    on(event, callback) {
      return this.subscribe(event, callback);
    }
    /** @alias unsubscribe */
    /** @deprecated */
    off(event, callback) {
      return this.unsubscribe(event, callback);
    }
  };

  // assets/ts/Core/MessageRenderer.ts
  var LEVEL_ICONS = {
    error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M7 7l6 6M13 7l-6 6" stroke-linecap="round"/></svg>',
    success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.5l2.5 2.5 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 3L2 17h16L10 3z" stroke-linejoin="round"/><path d="M10 8v4" stroke-linecap="round"/><circle cx="10" cy="14.5" r=".75" fill="currentColor" stroke="none"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="10" cy="10" r="8"/><path d="M10 9v5" stroke-linecap="round"/><circle cx="10" cy="6.5" r=".75" fill="currentColor" stroke="none"/></svg>'
  };
  var CLOSE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>';
  var LEVEL_MODIFIER = {
    error: NOTIFICATION.ERROR,
    success: NOTIFICATION.SUCCESS,
    warning: NOTIFICATION.WARNING,
    info: NOTIFICATION.INFO
  };
  var DEFAULT_TITLES = {
    error: "Chyba",
    success: "\xDAsp\u011Bch",
    warning: "Varov\xE1n\xED",
    info: "Informace"
  };
  var DEFAULTS = {
    maxVisible: 5,
    autoHideDelay: 8e3,
    verbose: false,
    debug: false
  };
  var MessageRenderer = class {
    options;
    container = null;
    timers = /* @__PURE__ */ new Map();
    active = [];
    constructor(options = {}) {
      this.options = { ...DEFAULTS, ...options };
    }
    // ─── Public API ─────────────────────────────────────
    /**
     * Zobrazí notifikaci a vrátí její element.
     */
    show(message) {
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
    dismiss(el) {
      if (!el.parentNode) return;
      const timer = this.timers.get(el);
      if (timer) {
        clearTimeout(timer);
      }
      const remove = () => {
      };
    }
    /**
     * Odstraní všechny aktivní notifikace.
     */
    clear() {
      for (const el of [...this.active]) {
      }
    }
    // ─── Private ────────────────────────────────────────
    /**
     * Zajistí existenci `.pb-notifications` kontejneru.
     * Vytvoří ho lazy při prvním volání show().
     */
    ensureContainer() {
      if (this.container && document.body.contains(this.container)) {
        return this.container;
      }
      let container = document.querySelector(`.${NOTIFICATION.CONTAINER}`);
      if (!container) {
        container = document.createElement("div");
        container.className = NOTIFICATION.CONTAINER;
        document.body.appendChild(container);
      }
      this.container = container;
      return container;
    }
    /**
     * Odstraní nejstarší notifikace pokud je překročen limit.
     */
    enforceLimit() {
      while (this.active.length >= this.options.maxVisible) {
        const oldest = this.active[0];
      }
    }
    /**
     * Sestaví DOM element notifikace.
     */
    build(message) {
      const el = document.createElement("div");
      el.className = `${NOTIFICATION.ROOT} ${LEVEL_MODIFIER[message.level]}`;
      el.setAttribute("role", message.level === "error" ? "alert" : "status");
      const title = this.escapeHtml(message.title || DEFAULT_TITLES[message.level]);
      const text = this.escapeHtml(message.message);
      let serverMsgHtml = "";
      let detailHtml = "";
      if ((this.options.verbose || this.options.debug) && message.detail != null) {
        const raw = typeof message.detail === "string" ? message.detail : JSON.stringify(message.detail, null, 2);
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
			<button class="${NOTIFICATION.CLOSE}" type="button" aria-label="Zav\u0159\xEDt">${CLOSE_ICON}</button>
		`;
      const closeBtn = el.querySelector(`.${NOTIFICATION.CLOSE}`);
      closeBtn?.addEventListener("click", () => this.dismiss(el));
      return el;
    }
    escapeHtml(str) {
      const div = document.createElement("div");
      div.textContent = str;
      return div.innerHTML;
    }
  };

  // assets/ts/Types/Api.ts
  var ErrorCode = {
    INVALID_REQUEST: 1001,
    VALIDATION: 1002,
    CONNECTION: 1003,
    TIMEOUT: 1004,
    INVALID_RESPONSE: 1005,
    API: 1006,
    // SecurityException kódy (2001–2003)
    INVALID_SIGNATURE: 2001,
    EXPIRED_TOKEN: 2002,
    MISSING_TOKEN: 2003,
    // JsonException kódy (3001–3003)
    JSON_DEPTH: 3001,
    INVALID_JSON: 3002,
    JSON_UTF8: 3003
  };

  // assets/ts/Core/ErrorHandler.ts
  var HTTP_MESSAGES = {
    400: "Neplatn\xFD po\u017Eadavek.",
    401: "Neautorizovan\xFD p\u0159\xEDstup.",
    403: "P\u0159\xEDstup zam\xEDtnut \u2014 neplatn\xFD bezpe\u010Dnostn\xED podpis.",
    404: "API endpoint nenalezen.",
    405: "Nepodporovan\xE1 HTTP metoda.",
    408: "Po\u017Eadavek vypr\u0161el.",
    422: "Chyba validace vstupn\xEDch dat.",
    429: "P\u0159\xEDli\u0161 mnoho po\u017Eadavk\u016F. Zkuste to pozd\u011Bji.",
    500: "Intern\xED chyba serveru.",
    502: "Server do\u010Dasn\u011B nedostupn\xFD.",
    503: "Slu\u017Eba je do\u010Dasn\u011B nedostupn\xE1.",
    504: "Po\u017Eadavek vypr\u0161el \u2014 server neodpov\u011Bd\u011Bl v\u010Das."
  };
  var CODE_TITLES = {
    [ErrorCode.INVALID_REQUEST]: "Neplatn\xFD po\u017Eadavek",
    [ErrorCode.VALIDATION]: "Chyba validace",
    [ErrorCode.CONNECTION]: "Chyba p\u0159ipojen\xED",
    [ErrorCode.TIMEOUT]: "\u010Casov\xFD limit vypr\u0161el",
    [ErrorCode.INVALID_RESPONSE]: "Neplatn\xE1 odpov\u011B\u010F",
    [ErrorCode.API]: "Chyba API",
    // SecurityException titulky
    [ErrorCode.INVALID_SIGNATURE]: "Bezpe\u010Dnostn\xED chyba",
    [ErrorCode.EXPIRED_TOKEN]: "Token vypr\u0161el",
    [ErrorCode.MISSING_TOKEN]: "Chyb\u011Bj\xEDc\xED token",
    // JsonException titulky
    [ErrorCode.INVALID_JSON]: "Neplatn\xE9 v\xFDstupn\xED data",
    [ErrorCode.JSON_DEPTH]: "Neplatn\xE9 v\xFDstupn\xED data",
    [ErrorCode.JSON_UTF8]: "Neplatn\xE9 v\xFDstupn\xED data"
  };
  var CODE_USER_MESSAGES = {
    // AiException (1001–1006)
    [ErrorCode.INVALID_REQUEST]: "Po\u017Eadavek obsahuje neplatn\xE1 data.",
    [ErrorCode.VALIDATION]: "Vstupn\xED data nepro\u0161la validac\xED.",
    [ErrorCode.CONNECTION]: "Nelze se p\u0159ipojit k AI poskytovateli.",
    [ErrorCode.TIMEOUT]: "Po\u017Eadavek na AI poskytovatele vypr\u0161el.",
    [ErrorCode.INVALID_RESPONSE]: "AI poskytovatel vr\xE1til neplatnou odpov\u011B\u010F.",
    [ErrorCode.API]: "AI poskytovatel vr\xE1til chybu.",
    // SecurityException (2001–2003)
    [ErrorCode.INVALID_SIGNATURE]: "Neplatn\xFD bezpe\u010Dnostn\xED podpis po\u017Eadavku.",
    [ErrorCode.EXPIRED_TOKEN]: "Platnost bezpe\u010Dnostn\xEDho tokenu vypr\u0161ela. Obnovte str\xE1nku.",
    [ErrorCode.MISSING_TOKEN]: "Po\u017Eadavek neobsahuje bezpe\u010Dnostn\xED token.",
    // JsonException (3001–3003)
    [ErrorCode.JSON_DEPTH]: "P\u0159ekro\u010Dena maxim\xE1ln\xED hloubka JSON.",
    [ErrorCode.INVALID_JSON]: "Neplatn\xFD form\xE1t JSON.",
    [ErrorCode.JSON_UTF8]: "Neplatn\xE9 UTF-8 znaky ve v\xFDstupu."
  };
  var CODE_TYPE_MAP = {
    [ErrorCode.INVALID_REQUEST]: "validation",
    [ErrorCode.VALIDATION]: "validation",
    [ErrorCode.CONNECTION]: "network",
    [ErrorCode.TIMEOUT]: "timeout",
    [ErrorCode.INVALID_RESPONSE]: "parse",
    [ErrorCode.API]: "api",
    [ErrorCode.INVALID_SIGNATURE]: "http",
    [ErrorCode.EXPIRED_TOKEN]: "http",
    [ErrorCode.MISSING_TOKEN]: "http",
    [ErrorCode.INVALID_JSON]: "parse",
    [ErrorCode.JSON_DEPTH]: "parse",
    [ErrorCode.JSON_UTF8]: "parse"
  };
  var TYPE_TITLES = {
    network: "Chyba s\xEDt\u011B",
    http: "Chyba serveru",
    api: "Chyba API",
    validation: "Chyba validace",
    parse: "Chyba zpracov\xE1n\xED",
    timeout: "\u010Casov\xFD limit",
    dom: "Chyba aplikace",
    unknown: "Neo\u010Dek\xE1van\xE1 chyba"
  };
  var HANDLER_DEFAULTS = {
    autoListen: true,
    verbose: false,
    debug: false,
    httpMessages: HTTP_MESSAGES,
    autoHideDelay: 1e4
  };
  var ErrorHandler = class {
    options;
    events;
    _renderer;
    normalizers = [];
    /** Ochrana proti re-entrancy při emit → subscribe cyklu */
    handling = false;
    constructor(events, options = {}) {
      this.events = events;
      const { rendererOptions, ...rest } = options;
      this.options = {
        ...HANDLER_DEFAULTS,
        ...rest,
        httpMessages: { ...HANDLER_DEFAULTS.httpMessages, ...rest.httpMessages }
      };
      this._renderer = new MessageRenderer({
        verbose: this.options.verbose,
        debug: this.options.debug,
        autoHideDelay: this.options.autoHideDelay,
        ...rendererOptions
      });
      if (this.options.autoListen) {
        this.listen();
      }
    }
    /** Přímý přístup k rendereru pro pokročilé použití */
    get renderer() {
      return this._renderer;
    }
    // ─── Rozšiřitelnost ────────────────────────────────
    /**
     * Registruje vlastní normalizátor chyb.
     * Normalizátory se volají v pořadí registrace – první non-null výsledek vyhrává.
     *
     * @example
     * // Rozšíření pro DOM eventy
     * handler.use((error) => {
     *     if (error instanceof SecurityPolicyViolationEvent) {
     *         return { type: 'dom', message: 'Porušení bezpečnostní politiky.', source: 'dom' };
     *     }
     *     return null;
     * });
     *
     * // Rozšíření pro vlastní error třídy
     * handler.use((error) => {
     *     if (error instanceof MyDomError) {
     *         return { type: 'dom', code: error.code, message: error.message, source: 'dom' };
     *     }
     *     return null;
     * });
     */
    use(normalizer) {
      this.normalizers.push(normalizer);
      return this;
    }
    // ─── Zpracování chyb ──────────────────────────────
    /**
     * Univerzální handler – normalizuje libovolnou chybu, emituje event a zobrazí notifikaci.
     *
     * Použitelné v catch blocích kdekoliv v aplikaci:
     * ```ts
     * try { ... } catch (e) { errorHandler.handle(e); }
     * ```
     */
    handle(error) {
      return this.process(this.normalize(error));
    }
    /**
     * Zpracuje HTTP response (s případným parsovaným body).
     * Vrátí null pokud response je OK a API nevrátila chybu.
     *
     * Pokrývá:
     * - HTTP 4xx/5xx s api.error detailem
     * - HTTP 200 ale api.success === false
     */
    handleResponse(response, body) {
      if (response.ok && (!body?.api || body.api.success)) return null;
      const showDetail = this.options.verbose || this.options.debug;
      if (!response.ok) {
        const apiError = body?.api?.error;
        const code = apiError?.code;
        const serverMessage = apiError?.message || void 0;
        const userMessage = code && CODE_USER_MESSAGES[code] ? this.options.debug && serverMessage ? serverMessage : CODE_USER_MESSAGES[code] : this.options.httpMessages[response.status] ?? `Chyba serveru (${response.status})`;
        return this.process({
          type: (code != null ? CODE_TYPE_MAP[code] : void 0) ?? "http",
          code: code ?? response.status,
          message: userMessage,
          serverMessage,
          source: "ajax",
          detail: showDetail ? {
            status: response.status,
            statusText: response.statusText,
            apiError
          } : void 0
        });
      }
      if (body?.api?.error) {
        return this.handleApiError(body.api.error);
      }
      return null;
    }
    /**
     * Zpracuje API chybu z backendu (api.error objekt).
     * Kódy chyb odpovídají PHP AiException (1001–1006), SecurityException (2001–2003)
     * a JsonException (3001–3003).
     *
     * - `message`       → user-friendly hláška z CODE_USER_MESSAGES (nebo fallback)
     * - `serverMessage` → originální PHP throw message (zobrazí se jen v debug módu)
     */
    handleApiError(apiError) {
      const showDetail = this.options.verbose || this.options.debug;
      const code = apiError.code;
      const serverMessage = apiError.message || void 0;
      const userMessage = code && CODE_USER_MESSAGES[code] ? this.options.debug && serverMessage ? serverMessage : CODE_USER_MESSAGES[code] : serverMessage || "API vr\xE1tila chybu.";
      return this.process({
        type: (code != null ? CODE_TYPE_MAP[code] : void 0) ?? "api",
        code,
        message: userMessage,
        serverMessage,
        source: "ajax",
        detail: showDetail ? apiError : void 0
      });
    }
    /**
     * Zpracuje neočekávanou odpověď serveru (HTML místo JSON, prázdné tělo atd.).
     * Pokrývá případ, kdy PHP hodí fatal error a vrátí HTML.
     */
    handleUnexpectedResponse(response, rawBody) {
      const showDetail = this.options.verbose || this.options.debug;
      return this.process({
        type: "parse",
        code: ErrorCode.INVALID_RESPONSE,
        message: "Server vr\xE1til neo\u010Dek\xE1vanou odpov\u011B\u010F.",
        source: "ajax",
        detail: showDetail ? {
          status: response.status,
          contentType: response.headers.get("content-type"),
          body: rawBody?.substring(0, 500)
        } : void 0
      });
    }
    // ─── Notifikace (obecné hlášky) ────────────────────
    /**
     * Zobrazí notifikaci libovolné úrovně.
     * Pro success, info, warning hlášky které nejsou chyby.
     *
     * @example
     * handler.notify('success', 'Data byla úspěšně uložena.');
     * handler.notify('info', 'Zpracování probíhá...', 'Průběh');
     * handler.notify('warning', 'Tato akce je nevratná.');
     */
    notify(level, message, title) {
      this._renderer.show({ level, message, title });
      this.events.publish("notification", { level, message, title });
    }
    /**
     * Odstraní všechny zobrazené notifikace.
     */
    dismiss() {
      this._renderer.clear();
    }
    // ─── EventBus naslouchání ─────────────────────────
    /**
     * Přihlásí se na EventBus 'error' eventy.
     * Voláno automaticky pokud autoListen === true.
     * Zachytává chyby emitované transporty (HttpTransport, SseTransport atd.).
     */
    listen() {
      this.events.subscribe("error", ({ error, statusCode }) => {
        if (this.handling) return;
        const enriched = {
          ...error,
          code: error.code ?? statusCode
        };
        this.render(enriched);
      });
    }
    // ─── Normalizace ──────────────────────────────────
    /**
     * Normalizuje libovolný typ chyby do AiBridgeError.
     */
    normalize(error) {
      if (this.isAiBridgeError(error)) return error;
      for (const normalizer of this.normalizers) {
        const result = normalizer(error);
        if (result) return result;
      }
      if (error instanceof DOMException && error.name === "AbortError") {
        return {
          type: "timeout",
          code: ErrorCode.TIMEOUT,
          message: "Po\u017Eadavek byl zru\u0161en nebo vypr\u0161el timeout."
        };
      }
      if (error instanceof TypeError) {
        return {
          type: "network",
          code: ErrorCode.CONNECTION,
          message: "Nelze se p\u0159ipojit k serveru. Zkontrolujte p\u0159ipojen\xED k internetu.",
          detail: this.options.verbose ? error.message : void 0
        };
      }
      if (error instanceof Error) {
        return {
          type: "unknown",
          message: error.message || "Nastala neo\u010Dek\xE1van\xE1 chyba.",
          detail: this.options.verbose ? error.stack : void 0
        };
      }
      if (typeof error === "string") {
        return { type: "unknown", message: error };
      }
      return {
        type: "unknown",
        message: "Nastala neo\u010Dek\xE1van\xE1 chyba.",
        detail: this.options.verbose ? error : void 0
      };
    }
    // ─── Interní zpracování ───────────────────────────
    /**
     * Emituje error event a zobrazí notifikaci.
     */
    process(error) {
      this.emit(error);
      this.render(error);
      return error;
    }
    /**
     * Emituje 'error' event na EventBus s re-entrancy ochranou.
     */
    emit(error) {
      this.handling = true;
      this.events.publish("error", { error, statusCode: error.code });
      this.handling = false;
    }
    /**
     * Zobrazí error notifikaci přes MessageRenderer.
     */
    render(error) {
      this._renderer.show({
        level: "error",
        title: this.resolveTitle(error),
        message: error.message,
        serverMessage: error.serverMessage,
        detail: error.detail
      });
    }
    /**
     * Rozhodne titulek na základě error kódu (AiException) nebo typu.
     */
    resolveTitle(error) {
      if (error.code && CODE_TITLES[error.code]) {
        return CODE_TITLES[error.code];
      }
      return TYPE_TITLES[error.type] ?? "Chyba";
    }
    /**
     * Type guard pro AiBridgeError.
     */
    isAiBridgeError(error) {
      return typeof error === "object" && error !== null && "type" in error && "message" in error && typeof error.message === "string";
    }
  };

  // assets/ts/Services/TransportRegistry.ts
  var TransportRegistry = class {
    transports = [];
    /** Zaregistruje transport a seřadí seznam podle priority */
    register(transport) {
      this.unregister(transport.name);
      this.transports.push(transport);
      this.transports.sort((a, b) => a.priority - b.priority);
    }
    /** Odebere transport podle jména */
    unregister(name) {
      this.transports = this.transports.filter((t) => t.name !== name);
    }
    /** Vrací seřazený seznam všech transportů */
    getAll() {
      return this.transports;
    }
    /** Vrací pouze transporty, které aktuálně mohou zpracovat request */
    getAvailable() {
      return this.transports.filter((t) => t.canHandle());
    }
    /** Vrací transport podle jména, nebo undefined */
    get(name) {
      return this.transports.find((t) => t.name === name);
    }
    /** Přeruší všechny transporty */
    abortAll() {
      for (const transport of this.transports) {
        transport.abort();
      }
    }
  };

  // assets/ts/Services/ApiClient.ts
  var RequestBuilder = class {
    constructor(client, transport) {
      this.client = client;
      this.transport = transport;
    }
    async send(payload) {
      return this.client.sendVia(this.transport, payload);
    }
  };
  var ApiClient = class {
    options;
    events;
    registry;
    middlewares = [];
    constructor(events, options = {}) {
      this.options = { allowFallback: true, ...options };
      this.events = events;
      this.registry = new TransportRegistry();
    }
    // ── Fluent configuration ─────────────────────────
    /** Zaregistruje transport (řadí se podle priority) */
    registerTransport(transport) {
      this.registry.register(transport);
      return this;
    }
    /** Odebere transport podle jména */
    unregisterTransport(name) {
      this.registry.unregister(name);
      return this;
    }
    /** Přidá middleware do pipeline */
    use(middleware) {
      this.middlewares.push(middleware);
      return this;
    }
    // ── Transport access ─────────────────────────────
    /** Přímý přístup k transportu pro runtime konfiguraci */
    transport(name) {
      return this.registry.get(name);
    }
    // ── Sending ──────────────────────────────────────
    /**
     * Odešle payload přes middleware pipeline,
     * automaticky vybere transport podle priority.
     */
    async send(payload) {
      return this.execute(payload);
    }
    /**
     * Vrátí RequestBuilder s vynuceným transportem.
     * Ostatní transporty slouží jako záloha (pokud je allowFallback true).
     *
     * @example
     * const result = await api.via('http').send(data);
     */
    via(transport) {
      return new RequestBuilder(this, transport);
    }
    /**
     * Interní metoda volaná z RequestBuilder.
     * @internal
     */
    async sendVia(transportName, payload) {
      return this.execute(payload, transportName);
    }
    /** Přeruší všechny probíhající požadavky */
    abort() {
      this.registry.abortAll();
    }
    /** Vrací true, pokud SSE transport právě streamuje */
    get isStreaming() {
      const sse = this.registry.get("sse");
      return sse?.isStreaming ?? false;
    }
    // ── Core execution ───────────────────────────────
    async execute(payload, forcedTransport) {
      const context = {
        payload,
        startTime: performance.now(),
        meta: {}
      };
      this.events.publish("request:start", { payload });
      let success = false;
      try {
        const result = await this.compose(
          this.middlewares,
          () => this.dispatch(context, forcedTransport),
          context
        );
        success = result !== null;
        if (result) {
          const duration = performance.now() - context.startTime;
          this.events.publish("success", { data: result, duration });
        }
        return result;
      } finally {
        const duration = performance.now() - context.startTime;
        this.events.publish("request:end", { success, duration });
      }
    }
    // ── Middleware pipeline (onion model) ─────────────
    compose(middlewares, handler, context) {
      let index = -1;
      const dispatch = (i) => {
        if (i <= index) {
          throw new Error("next() called multiple times");
        }
        index = i;
        const mw = middlewares[i];
        return mw ? mw(context, () => dispatch(i + 1)) : handler();
      };
      return dispatch(0);
    }
    // ── Transport dispatch ───────────────────────────
    async dispatch(context, forcedTransport) {
      const available = this.registry.getAvailable();
      if (available.length === 0) {
        console.warn("[ApiClient] \u017D\xE1dn\xE9 dostupn\xE9 transporty.");
        return null;
      }
      return forcedTransport ? this.dispatchForced(context, available, forcedTransport) : this.dispatchByPriority(context, available);
    }
    /**
     * Vynucený transport – zkusí specifikovaný první,
     * ostatní jako fallback pokud allowFallback === true.
     */
    async dispatchForced(context, available, forced) {
      const primary = available.find((t) => t.name === forced);
      if (primary) {
        context.transport = primary.name;
        const result = await primary.send(context.payload);
        if (result) return result;
      }
      if (!this.options.allowFallback) return null;
      const fallbacks = available.filter((t) => t.name !== forced);
      for (const transport of fallbacks) {
        this.events.publish("transport:fallback", {
          from: forced,
          to: transport.name
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
    async dispatchByPriority(context, available) {
      for (let i = 0; i < available.length; i++) {
        const transport = available[i];
        context.transport = transport.name;
        const result = await transport.send(context.payload);
        if (result) return result;
        if (!this.options.allowFallback) return null;
        const next = available[i + 1];
        if (next) {
          this.events.publish("transport:fallback", {
            from: transport.name,
            to: next.name
          });
        }
      }
      return null;
    }
    // ── Form data extraction (static utility) ────────
    static extractFormData(form) {
      const elements = Array.from(form.elements);
      return elements.filter(
        (el) => {
          if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) {
            return false;
          }
          return !!el.name && !el.disabled && el.type !== "button" && el.type !== "submit";
        }
      ).reduce((data, el) => {
        if (el instanceof HTMLInputElement) {
          if (el.type === "checkbox") {
            data[el.name] = el.checked;
          } else if (el.type === "radio") {
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
  };

  // assets/ts/Services/ApiErrorHandler.ts
  var PHP_TYPE_MESSAGES = {
    security: "P\u0159\xEDstup zam\xEDtnut \u2014 neplatn\xFD bezpe\u010Dnostn\xED podpis.",
    invalid_json: "Neplatn\xFD form\xE1t po\u017Eadavku.",
    ai_provider: "Chyba AI poskytovatele.",
    internal_error: "Intern\xED chyba serveru."
  };
  var PHP_TYPE_MAP = {
    security: "http",
    invalid_json: "validation",
    ai_provider: "api",
    internal_error: "unknown"
  };
  var AI_CODE_MESSAGES = {
    [ErrorCode.INVALID_REQUEST]: "Po\u017Eadavek obsahuje neplatn\xE1 data.",
    [ErrorCode.VALIDATION]: "Vstupn\xED data nepro\u0161la validac\xED.",
    [ErrorCode.CONNECTION]: "Nelze se p\u0159ipojit k AI poskytovateli.",
    [ErrorCode.TIMEOUT]: "Po\u017Eadavek na AI poskytovatele vypr\u0161el.",
    [ErrorCode.INVALID_RESPONSE]: "AI poskytovatel vr\xE1til neplatnou odpov\u011B\u010F.",
    [ErrorCode.API]: "AI poskytovatel vr\xE1til chybu.",
    // SecurityException kódy
    [ErrorCode.INVALID_SIGNATURE]: "Neplatn\xFD bezpe\u010Dnostn\xED podpis po\u017Eadavku.",
    [ErrorCode.EXPIRED_TOKEN]: "Platnost bezpe\u010Dnostn\xEDho tokenu vypr\u0161ela. Obnovte str\xE1nku.",
    [ErrorCode.MISSING_TOKEN]: "Po\u017Eadavek neobsahuje bezpe\u010Dnostn\xED token.",
    // JsonException kódy
    [ErrorCode.INVALID_JSON]: "Neplatn\xFD form\xE1t JSON.",
    [ErrorCode.JSON_DEPTH]: "P\u0159ekro\u010Dena maxim\xE1ln\xED hloubka JSON.",
    [ErrorCode.JSON_UTF8]: "Neplatn\xE9 UTF-8 znaky."
  };
  var AI_CODE_TYPE_MAP = {
    [ErrorCode.INVALID_REQUEST]: "validation",
    [ErrorCode.VALIDATION]: "validation",
    [ErrorCode.CONNECTION]: "network",
    [ErrorCode.TIMEOUT]: "timeout",
    [ErrorCode.INVALID_RESPONSE]: "parse",
    [ErrorCode.API]: "api",
    // SecurityException → http (403/401)
    [ErrorCode.INVALID_SIGNATURE]: "http",
    [ErrorCode.EXPIRED_TOKEN]: "http",
    [ErrorCode.MISSING_TOKEN]: "http",
    // JsonException → parse
    [ErrorCode.INVALID_JSON]: "parse",
    [ErrorCode.JSON_DEPTH]: "parse",
    [ErrorCode.JSON_UTF8]: "parse"
  };
  var HTTP_STATUS_MESSAGES = {
    400: "Neplatn\xFD po\u017Eadavek.",
    403: "P\u0159\xEDstup zam\xEDtnut.",
    404: "API endpoint nenalezen.",
    422: "Chyba validace vstupn\xEDch dat.",
    429: "P\u0159\xEDli\u0161 mnoho po\u017Eadavk\u016F \u2014 zkuste to pozd\u011Bji.",
    500: "Intern\xED chyba serveru.",
    502: "Server AI poskytovatele je do\u010Dasn\u011B nedostupn\xFD.",
    503: "Slu\u017Eba je do\u010Dasn\u011B nedostupn\xE1.",
    504: "Po\u017Eadavek na AI poskytovatele vypr\u0161el."
  };
  var DEFAULTS2 = {
    verbose: false,
    debug: false
  };
  var ApiErrorHandler = class {
    events;
    errorHandler;
    options;
    typeMessages;
    codeMessages;
    constructor(events, errorHandler, options = {}) {
      this.events = events;
      this.errorHandler = errorHandler;
      const { typeMessages, codeMessages, ...rest } = options;
      this.options = { ...DEFAULTS2, ...rest };
      this.typeMessages = { ...PHP_TYPE_MESSAGES, ...typeMessages };
      this.codeMessages = { ...AI_CODE_MESSAGES, ...codeMessages };
    }
    // ─── Hlavní entry points ──────────────────────────
    /**
     * Zpracuje fetch Response + rozparsované body.
     * Vrátí null pokud odpověď je úspěšná, jinak AiBridgeError.
     *
     * Volá se z HttpTransport po obdržení odpovědi:
     * ```ts
     * const error = apiErrors.handleResponse(response, body);
     * if (error) return null; // transport vrátí null = selhání
     * ```
     */
    handleResponse(response, body) {
      if (response.ok && (!body?.api || body.api.success)) {
        return null;
      }
      const apiError = body?.api?.error;
      if (!response.ok) {
        return this.processHttpError(response, apiError);
      }
      if (apiError) {
        return this.processApiError(apiError);
      }
      return null;
    }
    /**
     * Zpracuje neočekávanou odpověď (HTML místo JSON, prázdné tělo).
     * Pokrývá případ PHP fatal error → HTML output.
     */
    handleUnexpectedResponse(response, rawBody) {
      const showDetail = this.options.verbose || this.options.debug;
      const error = {
        type: "parse",
        code: ErrorCode.INVALID_RESPONSE,
        message: "Server vr\xE1til neo\u010Dek\xE1vanou odpov\u011B\u010F m\xEDsto JSON.",
        source: "api",
        detail: showDetail ? {
          status: response.status,
          contentType: response.headers.get("content-type"),
          bodyPreview: rawBody?.substring(0, 500)
        } : void 0
      };
      return this.emit(error);
    }
    /**
     * Zpracuje síťovou/transport chybu (TypeError, AbortError, generická Error).
     * Volá se z catch bloku v HttpTransport.
     */
    handleTransportError(error) {
      if (error instanceof DOMException && error.name === "AbortError") {
        return this.emit({
          type: "timeout",
          code: ErrorCode.TIMEOUT,
          message: "Po\u017Eadavek byl zru\u0161en nebo vypr\u0161el timeout.",
          source: "transport"
        });
      }
      if (error instanceof TypeError) {
        return this.emit({
          type: "network",
          code: ErrorCode.CONNECTION,
          message: "Nelze se p\u0159ipojit k serveru. Zkontrolujte p\u0159ipojen\xED k internetu.",
          source: "transport",
          detail: this.options.verbose || this.options.debug ? error.message : void 0
        });
      }
      return this.errorHandler.handle(error);
    }
    // ─── Interní zpracování ───────────────────────────
    /**
     * Zpracuje HTTP chybu (4xx/5xx) s volitelným API error detailem z PHP.
     */
    processHttpError(response, apiError) {
      if (apiError) {
        return this.processApiError(apiError, response.status);
      }
      const message = HTTP_STATUS_MESSAGES[response.status] ?? `Neo\u010Dek\xE1van\xE1 chyba serveru (${response.status}).`;
      const showDetail = this.options.verbose || this.options.debug;
      return this.emit({
        type: "http",
        code: response.status,
        message,
        source: "api",
        detail: showDetail ? {
          status: response.status,
          statusText: response.statusText
        } : void 0
      });
    }
    /**
     * Zpracuje API error objekt z PHP odpovědi.
     *
     * Rozpoznává:
     * 1. AiException / JsonException kódy (1001–1006, 3001–3003) → specifické hlášky a typy
     * 2. PHP error type (security, invalid_json…) → mapované hlášky
     * 3. Fallback na raw message z PHP
     *
     * `message` je vždy klientsky přívětivá mapovaná zpráva.
     * `serverMessage` je vždy originální PHP zpráva (dostupná pro debug zobrazení).
     */
    processApiError(apiError, httpStatus) {
      const code = apiError.code;
      const phpType = apiError.type;
      const serverMessage = apiError.message || void 0;
      const showDetail = this.options.verbose || this.options.debug;
      if (code && this.codeMessages[code]) {
        return this.emit({
          type: AI_CODE_TYPE_MAP[code] ?? "api",
          code,
          message: this.codeMessages[code],
          serverMessage,
          source: "api",
          detail: showDetail ? apiError : void 0
        });
      }
      if (phpType && this.typeMessages[phpType]) {
        return this.emit({
          type: PHP_TYPE_MAP[phpType] ?? "unknown",
          code: code ?? httpStatus,
          message: this.typeMessages[phpType],
          serverMessage,
          source: "api",
          detail: showDetail ? apiError : void 0
        });
      }
      return this.emit({
        type: "api",
        code: code ?? httpStatus,
        message: serverMessage || "API vr\xE1tila nespecifikovanou chybu.",
        serverMessage,
        source: "api",
        detail: showDetail ? apiError : void 0
      });
    }
    /**
     * Emituje chybu přes ErrorHandler (EventBus + notifikace) a vrátí ji.
     */
    emit(error) {
      return this.errorHandler.handle(error);
    }
  };

  // assets/ts/Services/SessionManager.ts
  var SessionManager = class {
    /** Aktuální relace */
    currentSession = null;
    /**
     * Uloží data z hlavní generace jako novou relaci.
     * Volá se po úspěšném odeslání hlavního formuláře.
     *
     * @param formData Objekt s daty formuláře, která mají být uložena do relace
     */
    save(formData) {
      this.currentSession = {
        formData: { ...formData },
        createdAt: Date.now()
      };
    }
    /**
     * Vrátí aktuální relaci nebo null.
     *
     * @returns Objekt aktuální relace, nebo null pokud žádná neexistuje
     */
    get() {
      return this.currentSession;
    }
    /**
     * Vrátí formulářová data z aktuální relace.
     *
     * @returns Objekt s formulářovými daty, nebo null pokud relace neexistuje
     */
    getFormData() {
      return this.currentSession?.formData ?? null;
    }
    /**
     * Sestaví data pro regeneraci jednoho klíče.
     *
     * Vezme uložená formulářová data z relace a přidá __generate_key,
     * čímž backendu signalizuje single-key mód.
     *
     * @param generateKey Klíč k regeneraci (např. "subject", "preheader")
     * @returns Data pro odeslání do API, nebo null pokud relace neexistuje
     */
    buildSingleKeyPayload(generateKey) {
      const formData = this.getFormData();
      if (!formData) {
        return null;
      }
      return {
        ...formData,
        __generate_key: generateKey
      };
    }
    /**
     * Zkontroluje, zda existuje aktivní relace.
     */
    hasSession() {
      return this.currentSession !== null;
    }
    /**
     * Vrátí stáří relace v milisekundách.
     */
    getAge() {
      if (!this.currentSession) {
        return null;
      }
      return Date.now() - this.currentSession.createdAt;
    }
    /**
     * Vymaže relaci.
     */
    clear() {
      this.currentSession = null;
    }
  };

  // assets/ts/Features/FormValidator.ts
  var DEFAULTS3 = {
    errorClass: MODULE.FIELD_ERROR,
    errorMessageClass: MODULE.ERROR_MSG,
    fieldWrapperSelector: `.${MODULE.FIELD}`,
    requiredOnly: true,
    messages: {},
    defaultMessage: "Toto pole je povinn\xE9"
  };
  var FormValidator = class {
    options;
    events;
    constructor(events, options = {}) {
      this.events = events;
      this.options = { ...DEFAULTS3, ...options };
    }
    // ─── Hlavní validace ───────────────────────────────────────────
    /**
     * Zvaliduje celý formulář.
     * Vrací true pokud je vše OK, false pokud jsou chyby.
     */
    validate(form) {
      this.clearErrors(form);
      const errors = this.collectErrors(form);
      if (errors.length > 0) {
        this.showErrors(form, errors);
        this.events.publish("validation", { errors });
        return false;
      }
      return true;
    }
    // ─── Sběr chyb ────────────────────────────────────────────────
    /**
     * Projde formulářové prvky a vrátí seznam chyb
     */
    collectErrors(form) {
      const errors = [];
      const elements = Array.from(form.elements);
      for (const el of elements) {
        if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) {
          continue;
        }
        if (el.disabled || el.type === "hidden" || el.type === "button" || el.type === "submit") {
          continue;
        }
        if (this.options.requiredOnly && !el.required) {
          continue;
        }
        if (!el.name) {
          continue;
        }
        if (this.isEmpty(el)) {
          errors.push({
            field: el.name,
            message: this.getErrorMessage(el.name)
          });
        }
      }
      return errors;
    }
    /**
     * Kontrola, zda je prvek prázdný
     */
    isEmpty(el) {
      switch (el.type) {
        case "checkbox":
          return !el.checked;
        case "radio":
          const form = el.closest("form");
          if (!form) return !el.checked;
          const radios = form.querySelectorAll(`input[name="${el.name}"]`);
          return !Array.from(radios).some((r) => r.checked);
        case "select-one":
        case "select-multiple":
          return !el.value || el.value === "";
        default:
          return el.value.trim() === "";
      }
    }
    // ─── Zobrazení / vymazání chyb ─────────────────────────────────
    /**
     * Zobrazí chyby u příslušných polí
     */
    showErrors(form, errors) {
      for (const error of errors) {
        const field = form.querySelector(`[name="${error.field}"]`);
        if (!field) continue;
        const wrapper = field.closest(this.options.fieldWrapperSelector) || field.parentElement;
        if (wrapper) {
          wrapper.classList.add(this.options.errorClass);
        }
        const errorEl = document.createElement("span");
        errorEl.className = this.options.errorMessageClass;
        errorEl.textContent = error.message;
        errorEl.setAttribute("data-validation-error", error.field);
        field.insertAdjacentElement("afterend", errorEl);
      }
      const firstError = errors[0];
      if (firstError) {
        const firstField = form.querySelector(`[name="${firstError.field}"]`);
        firstField?.focus();
      }
    }
    /**
     * Vymaže všechny validační chyby z formuláře
     */
    clearErrors(form) {
      form.querySelectorAll(`.${this.options.errorClass}`).forEach((el) => {
        el.classList.remove(this.options.errorClass);
      });
      form.querySelectorAll(`[data-validation-error]`).forEach((el) => {
        el.remove();
      });
    }
    /**
     * Vrátí chybovou zprávu pro pole
     */
    getErrorMessage(fieldName) {
      return this.options.messages[fieldName] || this.options.defaultMessage;
    }
    /**
     * Nastaví real-time validaci (odstraní error při vyplnění)
     */
    enableLiveValidation(form) {
      form.addEventListener("input", (e) => {
        const target = e.target;
        if (!(target instanceof HTMLInputElement || target instanceof HTMLSelectElement || target instanceof HTMLTextAreaElement)) {
          return;
        }
        if (!this.isEmpty(target)) {
          const wrapper = target.closest(this.options.fieldWrapperSelector) || target.parentElement;
          wrapper?.classList.remove(this.options.errorClass);
          const errorMsg = form.querySelector(`[data-validation-error="${target.name}"]`);
          errorMsg?.remove();
        }
      });
      form.addEventListener("change", (e) => {
        const target = e.target;
        if (target instanceof HTMLSelectElement || target instanceof HTMLInputElement && (target.type === "checkbox" || target.type === "radio")) {
          if (!this.isEmpty(target)) {
            const wrapper = target.closest(this.options.fieldWrapperSelector) || target.parentElement;
            wrapper?.classList.remove(this.options.errorClass);
            const errorMsg = form.querySelector(`[data-validation-error="${target.name}"]`);
            errorMsg?.remove();
          }
        }
      });
    }
  };

  // assets/ts/Features/VisibilityController.ts
  var VisibilityController = class _VisibilityController {
    /** Selektor pro bloky s podmíněnou viditelností */
    static ATTR = "data-visible-if";
    /** CSS třída přidaná na skrytý blok (umožňuje animace) */
    static HIDDEN_CLASS = MODULE.BLOCK_HIDDEN;
    /** Všechny aktivní bindingy */
    bindings = [];
    /** Formulář, ke kterému je controller vázán */
    form;
    /** Reference na event handler pro pozdější unbind */
    changeHandler;
    constructor(form) {
      this.form = form;
      this.changeHandler = this.onFieldChange.bind(this);
      this.discover();
      this.bindEvents();
      this.evaluateAll();
    }
    // ─── Statické API ──────────────────────────────────────────────
    /**
     * Inicializuje VisibilityController pro všechny formuláře (nebo konkrétní).
     *
     * @param scope Volitelný kořenový element (výchozí: document)
     * @returns Pole instancí controlleru
     */
    static init(scope) {
      const root = scope ?? document;
      const forms = root.querySelectorAll("form");
      const controllers = [];
      forms.forEach((form) => {
        if (form.querySelector(`[${_VisibilityController.ATTR}]`)) {
          controllers.push(new _VisibilityController(form));
        }
      });
      return controllers;
    }
    // ─── Discovery ─────────────────────────────────────────────────
    /**
     * Najde všechny bloky s data-visible-if a vytvoří bindingy.
     */
    discover() {
      const blocks = this.form.querySelectorAll(`[${_VisibilityController.ATTR}]`);
      blocks.forEach((block) => {
        const raw = block.getAttribute(_VisibilityController.ATTR);
        if (!raw) return;
        try {
          const parsed = JSON.parse(raw);
          const conditions = Object.entries(parsed).map(
            ([fieldName, expectedValue]) => ({ fieldName, expectedValue })
          );
          if (conditions.length > 0) {
            this.bindings.push({ block, conditions });
          }
        } catch (e) {
          console.warn("[VisibilityController] Neplatn\xFD JSON v data-visible-if:", raw, e);
        }
      });
    }
    // ─── Event binding ─────────────────────────────────────────────
    /**
     * Naváže change event listener na formulář (event delegation).
     *
     * Reaguje na:
     * - radio: change event
     * - checkbox: change event
     */
    bindEvents() {
      this.form.addEventListener("change", this.changeHandler);
    }
    /**
     * Handler pro change event – vyhodnotí podmínky při změně pole.
     */
    onFieldChange(e) {
      const target = e.target;
      if (!(target instanceof HTMLInputElement)) return;
      if (target.type !== "radio" && target.type !== "checkbox") return;
      const fieldName = target.name;
      this.bindings.forEach((binding) => {
        const isRelevant = binding.conditions.some((c) => c.fieldName === fieldName);
        if (isRelevant) {
          this.evaluate(binding);
        }
      });
    }
    // ─── Evaluace ──────────────────────────────────────────────────
    /**
     * Vyhodnotí všechny bindingy (voláno při inicializaci).
     */
    evaluateAll() {
      this.bindings.forEach((binding) => this.evaluate(binding));
    }
    /**
     * Vyhodnotí jeden binding – zobrazí nebo skryje blok.
     *
     * Všechny podmínky musí být splněny (AND logika).
     */
    evaluate(binding) {
      const allMet = binding.conditions.every(
        (condition) => this.checkCondition(condition)
      );
      this.toggleBlock(binding.block, allMet);
    }
    /**
     * Zkontroluje jednu podmínku proti aktuálnímu stavu formuláře.
     */
    checkCondition(condition) {
      const { fieldName, expectedValue } = condition;
      const fields = this.form.querySelectorAll(`[name="${fieldName}"]`);
      if (fields.length === 0) {
        console.warn(`[VisibilityController] Pole "${fieldName}" nenalezeno ve formul\xE1\u0159i.`);
        return false;
      }
      const firstField = fields[0];
      if (firstField.type === "checkbox") {
        return this.checkCheckbox(firstField, expectedValue);
      }
      if (firstField.type === "radio") {
        return this.checkRadioGroup(fields, expectedValue);
      }
      return firstField.value === String(expectedValue);
    }
    /**
     * Vyhodnotí podmínku pro checkbox.
     *
     * Podporuje:
     * - boolean: true/false → checked/unchecked
     * - string: porovná s value checkboxu (pokud je checked)
     */
    checkCheckbox(field, expected) {
      if (typeof expected === "boolean") {
        return field.checked === expected;
      }
      return field.checked && field.value === expected;
    }
    /**
     * Vyhodnotí podmínku pro radio group.
     *
     * Hledá aktuálně vybraný radio a porovná jeho value s očekávanou hodnotou.
     */
    checkRadioGroup(fields, expected) {
      const checked = Array.from(fields).find((r) => r.checked);
      if (!checked) return false;
      return checked.value === String(expected);
    }
    // ─── DOM manipulace ────────────────────────────────────────────
    /**
     * Zobrazí nebo skryje blok.
     *
     * Používá CSS třídu a aria-hidden pro přístupnost.
     * Při skrytí nastaví disabled na všechny formulářové prvky uvnitř bloku,
     * aby se neodesílaly při submit.
     */
    toggleBlock(block, visible) {
      block.classList.toggle(_VisibilityController.HIDDEN_CLASS, !visible);
      block.setAttribute("aria-hidden", String(!visible));
      const formElements = block.querySelectorAll(
        "input, select, textarea"
      );
      formElements.forEach((el) => {
        if (!visible) {
          if (!el.hasAttribute("data-visibility-disabled")) {
            el.setAttribute("data-visibility-disabled", el.disabled ? "true" : "false");
          }
          el.disabled = true;
        } else {
          const wasDisabled = el.getAttribute("data-visibility-disabled");
          if (wasDisabled !== null) {
            el.disabled = wasDisabled === "true";
            el.removeAttribute("data-visibility-disabled");
          }
        }
      });
    }
    // ─── Cleanup ───────────────────────────────────────────────────
    /**
     * Zruší všechny bindingy a event listenery.
     */
    destroy() {
      this.form.removeEventListener("change", this.changeHandler);
      this.bindings = [];
    }
  };

  // assets/ts/Features/ResultActionHandler.ts
  var ResultActionHandler = class {
    constructor(events, api, session) {
      this.events = events;
      this.api = api;
      this.session = session;
    }
    /**
     * Inicializuje delegovaný event listener na result kontejneru.
     * Používá event delegation — stačí navěsit jednou,
     * funguje i pro dynamicky přidané výsledky.
     */
    init() {
      Dom.delegate("click", ".pb-module [data-action]", (el, e) => {
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
    parseAction(button) {
      const action = button.attr("data-action");
      const flags = button.attr("role");
      if (!action || !flags) return null;
      const parts = flags.split(" ");
      const key = parts[0] ?? "";
      const index = parseInt(parts[1] ?? "0", 10);
      return { action, key, index };
    }
    /**
     * Zpracuje akci podle typu na základě hodnoty action.action.
     * Podle typu akce zavolá odpovídající metodu nebo vypíše informaci do konzole.
     *
     * @param action Objekt s informacemi o akci (typ, klíč, index)
     * @param button DomNode tlačítka, na kterém byla akce vyvolána
     */
    handleAction(action, button) {
      switch (action.action) {
        case "repeat":
          this.handleRepeat(action, button);
          break;
        case "copy":
          this.handleCopy(button);
          break;
        case "use":
          this.handleUse(action, button);
          break;
        case "thumb-up":
        case "thumb-down":
          console.log("Bude v budoucnu");
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
    async handleRepeat(action, button) {
      if (!this.session.hasSession()) {
        console.warn("[ResultAction] \u017D\xE1dn\xE1 aktivn\xED relace pro regeneraci.");
        return;
      }
      const wrapper = button.closest("[data-key]");
      if (!wrapper) return;
      const payload = this.session.buildSingleKeyPayload(action.key);
      if (!payload) return;
      const content = Dom.flag(FLAGS.RESULT_CONTENT, wrapper.el);
      button.prop("disabled", true);
      const MIN_LOADER_TIME = 300;
      const startTime = performance.now();
      try {
        const result = await this.api.via("http").send(payload);
        if (result?.mode === "http") {
          content.html(result.response.data.html);
        }
        this.events.publish("regenerate-key", {
          key: action.key,
          index: action.index,
          data: result ?? null
        });
      } catch (error) {
        console.error("[ResultAction] Chyba regenerace:", error);
      } finally {
        const elapsed = performance.now() - startTime;
        const remaining = MIN_LOADER_TIME - elapsed;
        if (remaining > 0) {
          await new Promise((resolve) => setTimeout(resolve, remaining));
        }
        button.prop("disabled", false);
      }
    }
    /**
     * Zkopíruje obsah výsledku do schránky.
     *
     * @param button DomNode tlačítka, na kterém byla akce vyvolána
     */
    async handleCopy(button) {
      const wrapper = button.closest("[data-key]");
      if (!wrapper) return;
      const content = Dom.flag("result-content", wrapper.el);
      const text = content.text().trim();
      if (!text) return;
      try {
        await navigator.clipboard.writeText(text);
        this.events.publish("copy", { success: true });
      } catch (error) {
        console.error("[ResultAction] Kop\xEDrov\xE1n\xED selhalo:", error);
        this.events.publish("copy", { success: false });
      }
    }
    /**
     * "Použít" — emituje event s klíčem a hodnotou pro cílovou aplikaci.
     */
    handleUse(action, button) {
      const wrapper = button.closest("[data-key]");
      if (!wrapper) return;
      const content = Dom.flag("result-content", wrapper.el);
      const text = content.text().trim();
      this.events.publish("pb-use", {
        data: {
          [action.key]: text
        }
      });
      button.removeClass("pb-module__button--primary");
      button.addClass("pb-module__button--used");
    }
    // /**
    //  * Feedback (thumb up/down) — emituje event.
    //  */
    // private handleFeedback(action: ResultAction, button: HTMLElement): void {
    // 	button.classList.toggle('is-active');
    // 	// Deaktivuj opačné tlačítko
    // 	const wrapper = button.closest<HTMLElement>('.pb-result__wrapper');
    // 	if (wrapper) {
    // 		const opposite = action.action === 'thumb-up' ? 'thumb-down' : 'thumb-up';
    // 		const oppositeBtn = wrapper.querySelector<HTMLElement>(`[action="${opposite}"]`);
    // 		oppositeBtn?.classList.remove('is-active');
    // 	}
    // }
  };

  // assets/ts/UI/CustomSelect.ts
  var CustomSelect = class _CustomSelect {
    /**
     * CSS třída pro hlavní wrapper custom selectu (BEM base)
     */
    static BASE_CLASS = SELECT.ROOT;
    /**
     * CSS třída pro stav otevřeného selectu
     */
    static OPEN_CLASS = SELECT.OPEN;
    /**
     * CSS třída pro vybranou možnost v dropdownu
     */
    static SELECTED_CLASS = SELECT.OPTION_SELECTED;
    /**
     * CSS třída pro stav disabled
     */
    static DISABLED_CLASS = SELECT.DISABLED;
    /**
     * Pomocná metoda pro generování BEM tříd (block__element--modifier)
     * @param element Název elementu (např. 'option', 'trigger')
     * @param mod Volitelný modifier (např. 'disabled', 'selected')
     * @returns Složená CSS třída
     */
    static cn(element, mod) {
      const base = _CustomSelect.BASE_CLASS;
      return element ? `${base}__${element}${mod ? `--${mod}` : ""}` : `${base}${mod ? `--${mod}` : ""}`;
    }
    /**
     * Odkaz na původní nativní <select> zabalený v DomNode
     */
    el;
    /**
     * Wrapper <div> kolem custom selectu
     */
    wrapper;
    /**
     * Tlačítko, které zobrazuje aktuální hodnotu a otevírá dropdown
     */
    trigger;
    /**
     * Dropdown <ul> s možnostmi
     */
    dropdown;
    /**
     * Pole všech možností (li) v dropdownu
     */
    options = [];
    /**
     * Je select aktuálně otevřený?
     */
    isOpen = false;
    /**
     * Index aktuálně fokusované možnosti v dropdownu
     */
    focusedIndex = -1;
    /**
     * Vytvoří instanci custom selectu pro daný <select> element.
     * 1. Zabalí nativní <select> do DomNode
     * 2. Pokud už je select zabalený (prevence duplikace), skončí
     * 3. Vytvoří wrapper, trigger a dropdown
     * 4. Namontuje do DOM, navěsí eventy a synchronizuje text
     */
    constructor(el) {
      this.el = Dom.wrap(el);
      if (el.closest(`.${_CustomSelect.BASE_CLASS}`)) return;
      this.wrapper = this.createWrapper();
      this.trigger = this.createTrigger();
      this.dropdown = this.createDropdown();
      this.mount();
      this.bindEvents();
      this.syncTriggerText();
    }
    /**
     * Vytvoří wrapper <div> kolem selectu, přenese všechny atributy a data-*
     * Pokud je select disabled, přidá příslušnou třídu
     */
    createWrapper() {
      const div = Dom.create("div", {
        attr: this.el.attr(),
        className: _CustomSelect.BASE_CLASS
      });
      if (this.el.prop("disabled")) {
        div.addClass(_CustomSelect.DISABLED_CLASS);
      }
      for (const [name, value] of Object.entries(this.el.attr())) {
        if (name.startsWith("data-")) {
          div.attr(name, value);
        }
      }
      return div;
    }
    /**
     * Vytvoří tlačítko, které zobrazuje aktuální hodnotu a otevírá dropdown
     * Nastaví ARIA atributy pro přístupnost
     * Pokud je select disabled, nastaví i disabled na tlačítku
     */
    createTrigger() {
      const btn = Dom.create("button", {
        className: _CustomSelect.cn("trigger"),
        attr: {
          type: "button",
          "aria-haspopup": "listbox",
          "aria-expanded": "false"
        }
      });
      if (this.el.prop("disabled")) btn.prop("disabled", true);
      return btn;
    }
    /**
     * Vytvoří dropdown <ul> a naplní ho <li> podle <option> v selectu
     * Každá možnost přenáší hodnotu, text, stav selected/disabled
     * Pokud je option vybraná, nastaví focusedIndex
     */
    createDropdown() {
      const ul = Dom.create("ul", {
        className: _CustomSelect.cn("dropdown"),
        attr: {
          role: "listbox"
        }
      });
      this.el.findAll("option").each((opt, i) => {
        const li = Dom.create("li", {
          className: _CustomSelect.cn("option"),
          attr: {
            role: "option"
          },
          data: {
            value: opt.val()
          },
          text: opt.text() ?? ""
        });
        if (opt.prop("disabled")) {
          li.addClass(_CustomSelect.cn("option", "disabled"));
          li.attr("aria-disabled", "true");
        }
        if (opt.prop("selected")) {
          li.addClass(_CustomSelect.SELECTED_CLASS);
          li.attr("aria-selected", "true");
          this.focusedIndex = i;
        }
        this.options.push(li);
        ul.append(li);
      });
      return ul;
    }
    /**
     * Namontuje custom select do DOM:
     * - vloží wrapper před původní select
     * - skryje nativní select (hidden, tabindex, aria-hidden)
     * - přidá třídu pro stylování
     * - vloží wrapper, trigger a dropdown do wrapperu
     */
    mount() {
      this.wrapper.insertBefore(this.el);
      this.el.attr("hidden", "true").attr("tabindex", "-1").attr("aria-hidden", "true");
      this.el.addClass(_CustomSelect.cn("native"));
      this.wrapper.append(this.el, this.trigger, this.dropdown);
    }
    /**
     * Provede callback pro každou možnost v dropdownu (li)
     * @param cb Funkce, která dostane každý <li> a jeho index
     */
    forEachOption(cb) {
      this.options.forEach(cb);
    }
    /**
     * Označí jako vybranou tu možnost, která má danou hodnotu
     * - Přidá/odebere CSS třídu a ARIA atribut podle shody
     * @param value Hodnota, která má být vybraná
     */
    setSelectedByValue(value) {
      this.forEachOption((opt) => {
        const selected = opt.data("value") === value;
        opt.toggleClass(_CustomSelect.SELECTED_CLASS, selected);
        if (selected) opt.attr("aria-selected", "true");
        else opt.removeAttr("aria-selected");
      });
    }
    /**
     * Navěsí všechny potřebné event listenery pro interakci s custom selectem:
     * - kliknutí, klávesy, klik mimo, změna hodnoty
     */
    bindEvents() {
      this.trigger.on("click", () => this.toggle());
      this.dropdown.on("click", (e) => {
        const target = e.target.closest(`.${_CustomSelect.cn("option")}`);
        if (!target || target.getAttribute("aria-disabled") === "true") return;
        const optNode = this.options.find((o) => o.el === target);
        if (optNode) this.selectOption(optNode);
      });
      this.trigger.on("keydown", (e) => this.handleKeyDown(e));
      this.dropdown.on("keydown", (e) => this.handleKeyDown(e));
      document.addEventListener("click", (e) => {
        if (this.isOpen && !this.wrapper.contains(e.target)) {
          this.close();
        }
      });
      this.el.on("change", () => this.syncFromNative());
    }
    /**
     * Přepne stav otevření dropdownu (otevře/zavře podle aktuálního stavu)
     */
    toggle() {
      this.isOpen ? this.close() : this.open();
    }
    /**
     * Otevře dropdown:
     * - nastaví stav, přidá CSS třídu, nastaví ARIA
     * - scrolluje na vybranou možnost
     * - ignoruje pokud je select disabled
     */
    open() {
      if (this.el.prop("disabled")) return;
      this.isOpen = true;
      this.wrapper.addClass(_CustomSelect.OPEN_CLASS);
      this.trigger.attr("aria-expanded", "true");
      const selected = this.dropdown.find(`.${_CustomSelect.SELECTED_CLASS}`);
      selected?.scrollIntoView({ block: "nearest" });
    }
    /**
     * Zavře dropdown:
     * - nastaví stav, odebere CSS třídu, nastaví ARIA
     * - resetuje fokus
     */
    close() {
      this.isOpen = false;
      this.wrapper.removeClass(_CustomSelect.OPEN_CLASS);
      this.trigger.attr("aria-expanded", "false");
      this.focusedIndex = -1;
    }
    /**
     * Vybere danou možnost v dropdownu:
     * - nastaví hodnotu, označí jako vybranou, vyvolá change
     * - aktualizuje text v triggeru, zavře dropdown a vrátí fokus
     * @param li Element <li> odpovídající vybrané možnosti
     */
    selectOption(li) {
      const value = li.data("value") ?? "";
      this.setSelectedByValue(value);
      this.el.val(value);
      this.el.trigger("change");
      this.syncTriggerText();
      this.close();
      this.trigger.focus();
    }
    /**
     * Obsluha klávesových událostí na triggeru/dropdownu:
     * - šipky pohybují fokusem
     * - Enter/mezerník vybírá možnost
     * - Escape/Tab zavírá dropdown
     */
    handleKeyDown(e) {
      switch (e.key) {
        case "ArrowDown":
          e.preventDefault();
          this.isOpen ? this.moveFocus(1) : this.open();
          break;
        case "ArrowUp":
          e.preventDefault();
          if (this.isOpen) this.moveFocus(-1);
          break;
        case "Enter":
        case " ":
          e.preventDefault();
          if (this.isOpen && this.focusedIndex >= 0) {
            const opt = this.options[this.focusedIndex];
            if (opt.attr("aria-disabled") !== "true") {
              this.selectOption(opt);
            }
          } else {
            this.open();
          }
          break;
        case "Escape":
          e.preventDefault();
          this.close();
          this.trigger.focus();
          break;
        case "Tab":
          this.close();
          break;
      }
    }
    /**
     * Posune fokus na další/předchozí povolenou možnost v dropdownu
     * @param delta +1 (doleva/dolů), -1 (doprava/nahoru)
     */
    moveFocus(delta) {
      const enabled = this.options.map((o, i) => o.attr("aria-disabled") !== "true" ? i : -1).filter((i) => i !== -1);
      if (!enabled.length) return;
      const pos = enabled.indexOf(this.focusedIndex);
      let next = pos + delta;
      if (next < 0) next = enabled.length - 1;
      if (next >= enabled.length) next = 0;
      this.focusedIndex = enabled[next];
      this.forEachOption((opt) => opt.removeClass(_CustomSelect.cn("option", "focused")));
      this.options[this.focusedIndex].addClass(_CustomSelect.cn("option", "focused"));
      this.options[this.focusedIndex].scrollIntoView({ block: "nearest" });
    }
    /**
     * Nastaví text v triggeru podle aktuálně vybrané možnosti v nativním <select>
     * (synchronizuje z backendu nebo při změně hodnoty)
     */
    syncTriggerText() {
      const selected = Dom.wrap(this.el.el.selectedOptions[0]);
      this.trigger.text(selected?.text() ?? "");
    }
    /**
     * Synchronizuje stav custom selectu podle hodnoty v nativním <select>:
     * - označí správnou možnost v dropdownu
     * - aktualizuje text v triggeru
     */
    syncFromNative() {
      this.setSelectedByValue(this.el.val());
      this.syncTriggerText();
    }
    /**
     * Nastaví hodnotu selectu (programově) a synchronizuje custom UI
     * @param value Nová hodnota, která má být vybraná
     */
    setValue(value) {
      this.el.val(value);
      this.syncFromNative();
    }
    /**
     * Vrátí aktuální hodnotu selectu
     */
    getValue() {
      return this.el.val();
    }
    /**
     * Zničí custom select a obnoví původní nativní <select> do DOM
     * - vrátí select zpět, odstraní skrytí a třídy, smaže wrapper
     */
    destroy() {
      this.wrapper.insertBefore(this.el);
      this.el.attr("hidden", "false").attr("tabindex", "0").removeAttr("aria-hidden");
      this.el.removeClass(_CustomSelect.cn("native"));
      this.wrapper.remove();
    }
    /**
     * Inicializuje custom selecty pro všechny <select> podle selektoru
     * @param selector CSS selektor (výchozí 'select')
     * @returns Pole instancí CustomSelect
     */
    static init(selector = "select") {
      return Array.from(document.querySelectorAll(selector)).map((el) => new _CustomSelect(el));
    }
  };

  // assets/ts/UI/LayoutController.ts
  var LayoutController = class _LayoutController {
    /** Selektor pro root element s data-controller="layout" */
    static ROOT_SELECTOR = '[data-controller="layout"]';
    /** Selektor pro sekce s definovanými sloupci nebo column template */
    static SECTION_SELECTOR = "[data-layout-columns], [data-layout-column-template]";
    /** Selektor pro bloky s definovaným span */
    static BLOCK_SELECTOR = "[data-layout-span]";
    /** Selektor pro bloky s definovaným row span */
    static ROW_BLOCK_SELECTOR = "[data-layout-row-span]";
    /** Selektor pro bloky s explicitním grid-column */
    static GRID_COLUMN_SELECTOR = "[data-layout-grid-column]";
    /** Selektor pro bloky s explicitním grid-row */
    static GRID_ROW_SELECTOR = "[data-layout-grid-row]";
    /** CSS class přidaná sekcím s aktivním grid layoutem */
    static GRID_ACTIVE_CLASS = MODULE.SECTION_GRID;
    /** Root element */
    root;
    constructor(root) {
      this.root = root;
      this.init();
    }
    /**
     * Inicializuje layout na všech sekcích v rámci root elementu.
     */
    init() {
      const sections = this.root.findAll(_LayoutController.SECTION_SELECTOR);
      sections.each((section) => this.applyGridToSection(section));
    }
    /**
     * Aplikuje CSS Grid na danou sekci podle data-layout-columns a data-layout-column-template.
     *
     * @param section DomNode sekce, na kterou se má grid aplikovat
     */
    applyGridToSection(section) {
      const columnTemplate = section.data("layoutColumnTemplate");
      const columns = parseInt(section.data("layoutColumns") || "1", 10);
      if (columns <= 1 && !columnTemplate) {
        return;
      }
      section.css({
        display: "grid",
        gridTemplateColumns: columnTemplate ? columnTemplate : `repeat(auto-fit, minmax(max(100px, calc(100% / ${columns})), 1fr))`
      });
      section.addClass(_LayoutController.GRID_ACTIVE_CLASS);
      const blocks = section.findAll([
        _LayoutController.BLOCK_SELECTOR,
        _LayoutController.ROW_BLOCK_SELECTOR,
        _LayoutController.GRID_COLUMN_SELECTOR,
        _LayoutController.GRID_ROW_SELECTOR
      ].join(", "));
      blocks.each((block) => {
        if (block.data("layoutGridColumn")) {
          this.applyGridColumnToBlock(block);
        } else if (block.data("layoutSpan")) {
          this.applySpanToBlock(block, columns);
        }
        if (block.data("layoutGridRow")) {
          this.applyGridRowToBlock(block);
        } else if (block.data("layoutRowSpan")) {
          this.applyRowSpanToBlock(block);
        }
      });
    }
    /**
     * Aplikuje grid-column span na blok podle data-layout-span.
     * Pokud je hodnota větší než počet sloupců, omezí ji na maximum.
     * Pokud je span 1, žádný styl se nenastavuje (výchozí chování gridu).
     *
     * @param block Blok, na který se má aplikovat grid-column span
     * @param maxColumns Maximální počet sloupců v sekci (omezení pro span)
     */
    applySpanToBlock(block, maxColumns) {
      const span = parseInt(block.data("layoutSpan") || "1", 10);
      const effectiveSpan = Math.min(span, maxColumns);
      if (effectiveSpan > 1) {
        block.css("grid-column", `span ${effectiveSpan}`);
      }
    }
    /**
     * Aplikuje grid-row span na blok podle data-layout-row-span.
     * Pokud je hodnota větší než 1, nastaví CSS vlastnost grid-row na příslušný span.
     * Pokud je rowSpan 1, žádný styl se nenastavuje (výchozí chování gridu).
     *
     * @param block Blok, na který se má aplikovat grid-row span
     */
    applyRowSpanToBlock(block) {
      const rowSpan = parseInt(block.data("layoutRowSpan") || "1", 10);
      if (rowSpan > 1) {
        block.css("grid-row", `span ${rowSpan}`);
      }
    }
    /**
     * Aplikuje explicitní grid-column na blok.
     * Hodnota se přenese přímo z data-layout-grid-column (např. "1 / -1", "2 / 4").
     *
     * @param block Blok, na který se má aplikovat grid-column
     */
    applyGridColumnToBlock(block) {
      const gridColumn = block.data("layoutGridColumn");
      if (gridColumn) {
        block.css("grid-column", gridColumn);
      }
    }
    /**
     * Aplikuje explicitní grid-row na blok.
     * Hodnota se přenese přímo z data-layout-grid-row (např. "1 / -1", "2 / 4").
     *
     * @param block Blok, na který se má aplikovat grid-row
     */
    applyGridRowToBlock(block) {
      const gridRow = block.data("layoutGridRow");
      if (gridRow) {
        block.css("grid-row", gridRow);
      }
    }
    /**
     * Přepočítá layout (například po AJAX přidání nových bloků).
     */
    refresh() {
      this.destroy();
      this.init();
    }
    /**
     * Odstraní všechny inline grid styly a třídy (reset).
     */
    destroy() {
      const sections = this.root.findAll(_LayoutController.SECTION_SELECTOR);
      sections.each((section) => {
        section.removeCss(["display", "grid-template-columns"]);
        section.removeClass(_LayoutController.GRID_ACTIVE_CLASS);
        section.findAll([
          _LayoutController.BLOCK_SELECTOR,
          _LayoutController.ROW_BLOCK_SELECTOR,
          _LayoutController.GRID_COLUMN_SELECTOR,
          _LayoutController.GRID_ROW_SELECTOR
        ].join(", ")).each((block) => {
          block.removeCss(["grid-row", "grid-column"]);
        });
      });
    }
    /**
     * Statická factory – automaticky najde root element(y) a inicializuje controller.
     *
     * @returns Pole instancí LayoutController
     */
    static autoInit() {
      const roots = Dom.qa(_LayoutController.ROOT_SELECTOR);
      return roots.map((root) => new _LayoutController(Dom.wrap(root)));
    }
  };

  // assets/ts/Services/Transports/HttpTransport.ts
  var DEFAULTS4 = {
    timeout: 6e4,
    priority: 10,
    headers: {}
  };
  var HttpTransport = class {
    name = "http";
    priority;
    events;
    url;
    timeout;
    headers;
    apiErrors;
    controller;
    constructor(events, config) {
      this.events = events;
      this.url = config.url;
      this.timeout = config.timeout ?? DEFAULTS4.timeout;
      this.priority = config.priority ?? DEFAULTS4.priority;
      this.headers = { ...DEFAULTS4.headers, ...config.headers };
      this.apiErrors = config.apiErrorHandler;
    }
    canHandle() {
      return true;
    }
    async send(payload) {
      this.abort();
      this.controller = new AbortController();
      try {
        const response = await fetch(this.url, {
          method: "POST",
          headers: { "Content-Type": "application/json", ...this.headers },
          body: JSON.stringify(payload),
          signal: this.createSignal()
        });
        const body = await this.parseJson(response);
        if (this.apiErrors) {
          if (!body) {
            this.apiErrors.handleUnexpectedResponse(response);
            return null;
          }
          const error = this.apiErrors.handleResponse(response, body);
          if (error) return null;
        } else {
          if (!body) return null;
          if (!this.validateLegacy(response, body)) return null;
        }
        return { mode: "http", response: body };
      } catch (error) {
        if (this.apiErrors) {
          this.apiErrors.handleTransportError(error);
        }
        return null;
      } finally {
        this.controller = void 0;
      }
    }
    abort() {
      this.controller?.abort();
      this.controller = void 0;
    }
    // ─── Private ──────────────────────────────────────
    async parseJson(response) {
      return await response.json();
    }
    /** @deprecated Zpětná kompatibilita – použijte ApiErrorHandler */
    validateLegacy(response, body) {
      if (!response.ok) {
        const message = body.api?.error?.message ?? `Chyba serveru (${response.status})`;
        return false;
      }
      if (body.api && !body.api.success && body.api.error) {
        return false;
      }
      return true;
    }
    createSignal() {
      return AbortSignal.any([
        this.controller.signal,
        AbortSignal.timeout(this.timeout)
      ]);
    }
  };

  // assets/ts/Middleware/RetryMiddleware.ts
  function RetryMiddleware(retries = 2, delayMs = 0) {
    return async (__, next) => {
      let attempt = 0;
      while (attempt <= retries) {
        const result = await next();
        if (result) {
          return result;
        }
        attempt++;
        if (attempt <= retries) {
          console.warn(`[API] retry ${attempt}/${retries}`);
          if (delayMs > 0) {
            await new Promise((r) => setTimeout(r, delayMs));
          }
        }
      }
      return null;
    };
  }

  // assets/ts/Middleware/CacheMiddleware.ts
  function CacheMiddleware(ttl = 1e4) {
    const cache = /* @__PURE__ */ new Map();
    return async (ctx, next) => {
      const key = JSON.stringify(ctx.payload);
      const now = Date.now();
      const cached = cache.get(key);
      if (cached && cached.expire > now) {
        return cached.data;
      }
      const result = await next();
      if (result) {
        cache.set(key, { data: result, expire: now + ttl });
      }
      return result;
    };
  }

  // assets/ts/app.ts
  var App = class {
    events;
    api;
    session;
    validator;
    errorHandler;
    apiErrors;
    requestButton;
    resultContainer;
    form;
    constructor() {
      this.requestButton = Dom.action("send-request");
      this.resultContainer = Dom.component(COMPONENTS.RESULT_CONTAINER);
      this.form = Dom.qRequired("form");
    }
    pipeline = [
      { name: "DOM", time: performance.now(), step: () => this.initDom() },
      { name: "Core", time: performance.now(), step: () => this.initCore() },
      { name: "Services", time: performance.now(), step: () => this.initServices() },
      { name: "Features", time: performance.now(), step: () => this.initFeatures() },
      { name: "Bindings", time: performance.now(), step: () => this.bindEvents() }
      // { name: "Public API", time: performance.now(), step: () => this.exposePublicApi() },
    ];
    init() {
      for (const stage of this.pipeline) {
        console.log("Init: " + stage.name + " Time:", performance.now() - stage.time);
        stage.step();
      }
    }
    initDom() {
      CustomSelect.init(`select.${MODULE.FIELD}`);
      LayoutController.autoInit();
      VisibilityController.init();
    }
    initCore() {
      this.events = new EventBus(window);
      this.errorHandler = new ErrorHandler(this.events, { autoListen: true, debug: true });
      this.apiErrors = new ApiErrorHandler(this.events, this.errorHandler, { debug: true });
    }
    initServices() {
      const apiUrl = this.getApiUrl();
      this.api = new ApiClient(this.events, { allowFallback: true }).registerTransport(new HttpTransport(this.events, {
        url: apiUrl,
        timeout: 6e4,
        priority: 10,
        apiErrorHandler: this.apiErrors
      })).use(RetryMiddleware(2)).use(CacheMiddleware(1e4));
      this.session = new SessionManager();
      new ResultActionHandler(this.events, this.api, this.session).init();
    }
    initFeatures() {
      this.validator = new FormValidator(this.events, { requiredOnly: true });
    }
    bindEvents() {
      this.requestButton.on("click", (e) => this.submitForm(e));
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
    getApiUrl() {
      const module = document.querySelector(`.${MODULE.ROOT}`);
      const url = module?.dataset.apiUrl;
      if (!url) {
        console.warn("[PlatformBridge] data-api-url not found on .ai-module, using fallback");
        return "/public/platformbridge/api.php";
      }
      return url;
    }
    async submitForm(e) {
      e.preventDefault();
      if (!this.validator.validate(this.form)) {
        return;
      }
      const data = ApiClient.extractFormData(this.form);
      const result = await this.api.via("http").send(data);
      if (!result) return;
      this.session.save(data);
      switch (result.mode) {
        case "http":
          if (result.response.data?.html) {
            this.resultContainer.html(result.response.data.html);
          }
          break;
        case "sse":
          console.log(`[SSE] ${result.response.total} v\xFDsledk\u016F za ${result.response.duration}s`);
          break;
        case "websocket":
          console.log("[WS] Response:", result.response.data);
          break;
      }
    }
  };

  // assets/ts/pb-main.ts
  document.addEventListener("DOMContentLoaded", () => {
    const app = new App();
    app.init();
  });
})();
//# sourceMappingURL=pb-main.js.map
