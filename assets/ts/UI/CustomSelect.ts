import { DomNode, Dom } from "assets/ts/Core";

/**
 * CustomSelect – Převede nativní <select> na stylizovatelný custom select.
 *
 * Zachovává:
 * - Původní <select> jako skrytý (pro formuláře a přístupnost)
 * - Synchronizaci hodnoty mezi custom UI a nativním selectem
 * - Klávesovou navigaci (↑ ↓ Enter Escape)
 * - Zavření při kliknutí mimo
 *
 * Použití:
 *   CustomSelect.init('.ai-module__select'); // konkrétní selektor
 *   CustomSelect.init(); // všechny <select> na stránce
 */

export class CustomSelect {
    /**
     * CSS třída pro hlavní wrapper custom selectu (BEM base)
     */
    private static readonly BASE_CLASS = 'cs';
    /**
     * CSS třída pro stav otevřeného selectu
     */
    private static readonly OPEN_CLASS = 'cs--open';
    /**
     * CSS třída pro vybranou možnost v dropdownu
     */
    private static readonly SELECTED_CLASS = 'cs__option--selected';
    /**
     * CSS třída pro stav disabled
     */
    private static readonly DISABLED_CLASS = 'cs--disabled';

    /**
     * Pomocná metoda pro generování BEM tříd (block__element--modifier)
     * @param element Název elementu (např. 'option', 'trigger')
     * @param mod Volitelný modifier (např. 'disabled', 'selected')
     * @returns Složená CSS třída
     */
    private static cn(element?: string, mod?: string): string {
        const base = CustomSelect.BASE_CLASS;
        return element ? `${base}__${element}${mod ? `--${mod}` : ''}` : `${base}${mod ? `--${mod}` : ''}`;
    }

    /**
     * Odkaz na původní nativní <select> zabalený v DomNode
     */
    private el: DomNode<HTMLSelectElement>;
    /**
     * Wrapper <div> kolem custom selectu
     */
    private wrapper!: DomNode<HTMLDivElement>;
    /**
     * Tlačítko, které zobrazuje aktuální hodnotu a otevírá dropdown
     */
    private trigger!: DomNode<HTMLButtonElement>;
    /**
     * Dropdown <ul> s možnostmi
     */
    private dropdown!: DomNode<HTMLUListElement>;
    /**
     * Pole všech možností (li) v dropdownu
     */
    private options: DomNode<HTMLLIElement>[] = [];

    /**
     * Je select aktuálně otevřený?
     */
    private isOpen = false;
    /**
     * Index aktuálně fokusované možnosti v dropdownu
     */
    private focusedIndex = -1;

    /**
     * Vytvoří instanci custom selectu pro daný <select> element.
     * 1. Zabalí nativní <select> do DomNode
     * 2. Pokud už je select zabalený (prevence duplikace), skončí
     * 3. Vytvoří wrapper, trigger a dropdown
     * 4. Namontuje do DOM, navěsí eventy a synchronizuje text
     */
    constructor(el: HTMLSelectElement) {
        this.el = Dom.wrap(el) as DomNode<HTMLSelectElement>;

        // Pokud už je select uvnitř custom wrapperu, nedělej nic (prevence dvojité inicializace)
        if (el.closest(`.${CustomSelect.BASE_CLASS}`)) return;

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
    private createWrapper(): DomNode<HTMLDivElement> {
        const div = Dom.create('div', {
            attr: this.el.attr(),
            className: CustomSelect.BASE_CLASS,
        })

        if (this.el.prop('disabled')) {
            div.addClass(CustomSelect.DISABLED_CLASS);
        }

        // Přenést všechny data-* atributy z původního selectu
        for (const [name, value] of Object.entries(this.el.attr())) {
            if (name.startsWith('data-')) {
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
    private createTrigger(): DomNode<HTMLButtonElement> {
        const btn = Dom.create('button', {
            className: CustomSelect.cn('trigger'),
            attr: {
                type: 'button',
                'aria-haspopup': 'listbox',
                'aria-expanded': 'false'
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
    private createDropdown(): DomNode<HTMLUListElement> {
        const ul = Dom.create('ul', {
            className: CustomSelect.cn('dropdown'),
            attr: {
                role: 'listbox'
            }
        });

        this.el.findAll("option").each((opt, i) => {
            const li = Dom.create('li', {
                className: CustomSelect.cn('option'),
                attr: {
                    role: 'option'
                },
                data: {
                    value: opt.val()
                },
                 text: opt.text() ?? ''
            });

            if ((opt).prop('disabled')) {
                li.addClass(CustomSelect.cn('option', 'disabled'));
                li.attr('aria-disabled', 'true');
            }

            if (opt.prop('selected')) {
                li.addClass(CustomSelect.SELECTED_CLASS);
                li.attr('aria-selected', 'true');
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
    private mount(): void {
        this.wrapper.insertBefore(this.el);
        this.el.attr('hidden', 'true').attr('tabindex', '-1').attr('aria-hidden', 'true');

        this.el.addClass(CustomSelect.cn('native'));

        this.wrapper.append(this.el, this.trigger, this.dropdown);
    }

    /**
     * Provede callback pro každou možnost v dropdownu (li)
     * @param cb Funkce, která dostane každý <li> a jeho index
     */
    private forEachOption(cb: (opt: DomNode<HTMLLIElement>, i: number) => void): void {
        this.options.forEach(cb);
    }

    /**
     * Označí jako vybranou tu možnost, která má danou hodnotu
     * - Přidá/odebere CSS třídu a ARIA atribut podle shody
     * @param value Hodnota, která má být vybraná
     */
    private setSelectedByValue(value: string): void {
        this.forEachOption(opt => {
            const selected = opt.data('value') === value;

            opt.toggleClass(CustomSelect.SELECTED_CLASS, selected);

            if (selected) opt.attr('aria-selected', 'true');
            else opt.removeAttr('aria-selected');
        });
    }

    /**
     * Navěsí všechny potřebné event listenery pro interakci s custom selectem:
     * - kliknutí, klávesy, klik mimo, změna hodnoty
     */
    private bindEvents(): void {
        // Otevření/zavření dropdownu po kliknutí na trigger
        this.trigger.on('click', () => this.toggle());

        // Výběr možnosti kliknutím v dropdownu
        this.dropdown.on('click', (e: Event) => {
            const target = (e.target as HTMLElement).closest(`.${CustomSelect.cn('option')}`) as HTMLLIElement | null;
            // Ignoruj klik na disabled možnost
            if (!target || target.getAttribute('aria-disabled') === 'true') return;

            const optNode = this.options.find(o => o.el === target);
            if (optNode) this.selectOption(optNode);
        });

        // Klávesová navigace na triggeru i v dropdownu
        this.trigger.on('keydown', e => this.handleKeyDown(e));
        this.dropdown.on('keydown', e => this.handleKeyDown(e));

        // Zavření dropdownu při kliknutí mimo custom select
        document.addEventListener('click', e => {
            if (this.isOpen && !this.wrapper.contains(e.target as Node)) {
                this.close();
            }
        });

        // Synchronizace při změně hodnoty nativního selectu (např. programově)
        this.el.on('change', () => this.syncFromNative());
    }

    /**
     * Přepne stav otevření dropdownu (otevře/zavře podle aktuálního stavu)
     */
    private toggle(): void {
        this.isOpen ? this.close() : this.open();
    }

    /**
     * Otevře dropdown:
     * - nastaví stav, přidá CSS třídu, nastaví ARIA
     * - scrolluje na vybranou možnost
     * - ignoruje pokud je select disabled
     */
    private open(): void {
        if (this.el.prop('disabled')) return;

        this.isOpen = true;
        this.wrapper.addClass(CustomSelect.OPEN_CLASS);
        this.trigger.attr('aria-expanded', 'true');

        const selected = this.dropdown.find(`.${CustomSelect.SELECTED_CLASS}`);
        selected?.scrollIntoView({ block: 'nearest' });
    }

    /**
     * Zavře dropdown:
     * - nastaví stav, odebere CSS třídu, nastaví ARIA
     * - resetuje fokus
     */
    private close(): void {
        this.isOpen = false;
        this.wrapper.removeClass(CustomSelect.OPEN_CLASS);
        this.trigger.attr('aria-expanded', 'false');
        this.focusedIndex = -1;
    }

    /**
     * Vybere danou možnost v dropdownu:
     * - nastaví hodnotu, označí jako vybranou, vyvolá change
     * - aktualizuje text v triggeru, zavře dropdown a vrátí fokus
     * @param li Element <li> odpovídající vybrané možnosti
     */
    private selectOption(li: DomNode<HTMLLIElement>): void {
        const value = li.data('value') ?? '';
        this.setSelectedByValue(value);

        this.el.val(value);
        this.el.trigger('change');

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
    private handleKeyDown(e: KeyboardEvent): void {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.isOpen ? this.moveFocus(1) : this.open();
                break;

            case 'ArrowUp':
                e.preventDefault();
                if (this.isOpen) this.moveFocus(-1);
                break;

            case 'Enter':
            case ' ':
                e.preventDefault();
                if (this.isOpen && this.focusedIndex >= 0) {
                    const opt = this.options[this.focusedIndex];
                    if (opt.attr('aria-disabled') !== 'true') {
                        this.selectOption(opt);
                    }
                } else {
                    this.open();
                }
                break;

            case 'Escape':
                e.preventDefault();
                this.close();
                this.trigger.focus();
                break;

            case 'Tab':
                this.close();
                break;
        }
    }

    /**
     * Posune fokus na další/předchozí povolenou možnost v dropdownu
     * @param delta +1 (doleva/dolů), -1 (doprava/nahoru)
     */
    private moveFocus(delta: number): void {
        const enabled = this.options.map((o, i) => (o.attr('aria-disabled') !== 'true' ? i : -1)).filter(i => i !== -1);

        if (!enabled.length) return;

        const pos = enabled.indexOf(this.focusedIndex);
        let next = pos + delta;

        if (next < 0) next = enabled.length - 1;
        if (next >= enabled.length) next = 0;

        this.focusedIndex = enabled[next];

        this.forEachOption(opt => opt.removeClass(CustomSelect.cn('option', 'focused')));
        this.options[this.focusedIndex].addClass(CustomSelect.cn('option', 'focused'));
        this.options[this.focusedIndex].scrollIntoView({ block: 'nearest' });
    }

    /**
     * Nastaví text v triggeru podle aktuálně vybrané možnosti v nativním <select>
     * (synchronizuje z backendu nebo při změně hodnoty)
     */
    private syncTriggerText(): void {
        const selected = Dom.wrap(this.el.el.selectedOptions[0]);
        this.trigger.text(selected?.text() ?? '');
    }

    /**
     * Synchronizuje stav custom selectu podle hodnoty v nativním <select>:
     * - označí správnou možnost v dropdownu
     * - aktualizuje text v triggeru
     */
    private syncFromNative(): void {
        this.setSelectedByValue(this.el.val());
        this.syncTriggerText();
    }

    /**
     * Nastaví hodnotu selectu (programově) a synchronizuje custom UI
     * @param value Nová hodnota, která má být vybraná
     */
    setValue(value: string): void {
        this.el.val(value);
        this.syncFromNative();
    }

    /**
     * Vrátí aktuální hodnotu selectu
     */
    getValue(): string {
        return this.el.val();
    }

    /**
     * Zničí custom select a obnoví původní nativní <select> do DOM
     * - vrátí select zpět, odstraní skrytí a třídy, smaže wrapper
     */
    destroy(): void {
        this.wrapper.insertBefore(this.el);
        this.el.attr("hidden", "false").attr("tabindex", "0").removeAttr("aria-hidden");
        this.el.removeClass(CustomSelect.cn('native'));

        this.wrapper.remove();
    }

    /**
     * Inicializuje custom selecty pro všechny <select> podle selektoru
     * @param selector CSS selektor (výchozí 'select')
     * @returns Pole instancí CustomSelect
     */
    static init(selector = 'select'): CustomSelect[] {
        return Array.from(document.querySelectorAll<HTMLSelectElement>(selector)).map(el => new CustomSelect(el));
    }
}