import { Dom, DomNode } from 'assets/ts/Core';
import { MODULE } from 'assets/ts/Const';

/**
 * LayoutController – JS controller pro CSS Grid layout řízený data atributy.
 *
 * Čte data-layout-columns na sekcích a data-layout-span na blocích
 * a aplikuje příslušné CSS Grid vlastnosti pomocí inline stylů.
 *
 * Podporované data atributy:
 * - Sekce: data-layout-columns, data-layout-column-template
 * - Bloky: data-layout-span, data-layout-row-span, data-layout-grid-column, data-layout-grid-row
 */
export class LayoutController {
    /** Selektor pro root element s data-controller="layout" */
    private static readonly ROOT_SELECTOR = '[data-controller="layout"]';

    /** Selektor pro sekce s definovanými sloupci nebo column template */
    private static readonly SECTION_SELECTOR = '[data-layout-columns], [data-layout-column-template]';

    /** Selektor pro bloky s definovaným span */
    private static readonly BLOCK_SELECTOR = '[data-layout-span]';

    /** Selektor pro bloky s definovaným row span */
    private static readonly ROW_BLOCK_SELECTOR = '[data-layout-row-span]';

    /** Selektor pro bloky s explicitním grid-column */
    private static readonly GRID_COLUMN_SELECTOR = '[data-layout-grid-column]';

    /** Selektor pro bloky s explicitním grid-row */
    private static readonly GRID_ROW_SELECTOR = '[data-layout-grid-row]';

    /** CSS class přidaná sekcím s aktivním grid layoutem */
    private static readonly GRID_ACTIVE_CLASS = MODULE.SECTION_GRID;

    /** Root element */
    private root: DomNode<HTMLElement>;

    constructor(root: DomNode<HTMLElement>) {
        this.root = root;
        this.init();
    }

    /**
     * Inicializuje layout na všech sekcích v rámci root elementu.
     */
    private init(): void {
        const sections = this.root.findAll(LayoutController.SECTION_SELECTOR);

        sections.each((section) => this.applyGridToSection(section));
    }

	/**
	 * Aplikuje CSS Grid na danou sekci podle data-layout-columns a data-layout-column-template.
	 *
	 * @param section DomNode sekce, na kterou se má grid aplikovat
	 */
    private applyGridToSection(section: DomNode<HTMLElement>): void {
        const columnTemplate = section.data('layoutColumnTemplate');
        const columns = parseInt(section.data('layoutColumns') || '1', 10);

        if (columns <= 1 && !columnTemplate) {
            return; // Při 1 sloupci bez custom template nechci měnit stávající flex layout
        }

		section.css({
			display: "grid",
			gridTemplateColumns: columnTemplate
				? columnTemplate
				: `repeat(auto-fit, minmax(max(100px, calc(100% / ${columns})), 1fr))`
		});

        section.addClass(LayoutController.GRID_ACTIVE_CLASS);

		const blocks = section.findAll([
			LayoutController.BLOCK_SELECTOR,
			LayoutController.ROW_BLOCK_SELECTOR,
			LayoutController.GRID_COLUMN_SELECTOR,
			LayoutController.GRID_ROW_SELECTOR,
    	].join(', '));

		blocks.each((block) => {
			if (block.data('layoutGridColumn')) {
				this.applyGridColumnToBlock(block);
			} else if (block.data('layoutSpan')) {
				this.applySpanToBlock(block, columns);
			}

			if (block.data('layoutGridRow')) {
				this.applyGridRowToBlock(block);
			} else if (block.data('layoutRowSpan')) {
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
    private applySpanToBlock(block: DomNode<HTMLElement>, maxColumns: number): void {
        const span = parseInt(block.data('layoutSpan') || '1', 10);

        // Zajistíme, že span nepřekročí počet sloupců
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
    private applyRowSpanToBlock(block: DomNode<HTMLElement>): void {
        const rowSpan = parseInt(block.data('layoutRowSpan') || '1', 10);

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
    private applyGridColumnToBlock(block: DomNode<HTMLElement>): void {
        const gridColumn = block.data('layoutGridColumn');

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
    private applyGridRowToBlock(block: DomNode<HTMLElement>): void {
        const gridRow = block.data('layoutGridRow');

        if (gridRow) {
            block.css("grid-row", gridRow);
        }
    }

    /**
     * Přepočítá layout (například po AJAX přidání nových bloků).
     */
    public refresh(): void {
		this.destroy();
    	this.init();
    }

    /**
     * Odstraní všechny inline grid styly a třídy (reset).
     */
    public destroy(): void {
        const sections = this.root.findAll(LayoutController.SECTION_SELECTOR);

        sections.each((section) => {
            section.removeCss(['display', 'grid-template-columns']);
            section.removeClass(LayoutController.GRID_ACTIVE_CLASS);

			section.findAll([
				LayoutController.BLOCK_SELECTOR,
				LayoutController.ROW_BLOCK_SELECTOR,
				LayoutController.GRID_COLUMN_SELECTOR,
				LayoutController.GRID_ROW_SELECTOR,
			].join(', ')).each((block) => {
                block.removeCss(['grid-row', 'grid-column']);
            });
        });
    }

    /**
     * Statická factory – automaticky najde root element(y) a inicializuje controller.
     *
     * @returns Pole instancí LayoutController
     */
    public static autoInit(): LayoutController[] {
        const roots = Dom.qa(LayoutController.ROOT_SELECTOR);

        return roots.map((root) => new LayoutController(Dom.wrap(root)));
    }
}