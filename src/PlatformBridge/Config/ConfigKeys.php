<?php

declare(strict_types=1);

namespace PlatformBridge\Config;

/**
 * Definice klíčů používaných v konfiguračních JSON souborech.
 *
 * Enum zajišťuje typovou bezpečnost a jednotnost názvů klíčů
 * napříč celou aplikací.
 *
 */
enum ConfigKeys: string
{
    // =========================================================================
    // ROOT KEYS - klíče na root úrovni konfiguračních souborů
    // =========================================================================

    /** Root klíč pro bloky v blocks.json */
    case BLOCKS = 'blocks';

    /** Root klíč pro layouty v layouts.json */
    case LAYOUTS = 'layouts';

	/** Root klíč pro layout v generatoru (layout_ref) */
	case LAYOUT = 'layout';

    /** Root klíč pro generátory v generators.json */
    case GENERATORS = 'generators';

    // =========================================================================
    // COMMON KEYS - společné klíče napříč konfiguracemi
    // =========================================================================

    /** Identifikátor entity */
    case ID = 'id';

    /** Popisek / název */
    case LABEL = 'label';

    /** Název / jméno pole */
    case NAME = 'name';

    // =========================================================================
    // REFERENCE KEYS - klíče pro reference mezi entitami
    // =========================================================================

    /** Reference na blok */
    case REF = 'ref';

    /** Reference na layout (v generátoru) */
    case LAYOUT_REF = 'layout_ref';

    // =========================================================================
    // LAYOUT & SECTION KEYS - klíče pro layouty a sekce
    // =========================================================================

    /** Pole sekcí v layoutu */
    case SECTIONS = 'sections';

    /** Počet sloupců v layoutu/sekci */
    case COLUMNS = 'columns';

    /** Span bloku v grid layoutu (sloupcový) */
    case SPAN = 'span';

    /** Row span bloku v grid layoutu (řádkový) */
    case ROW_SPAN = 'row_span';

    /** Explicitní grid-column hodnota bloku (např. "1 / -1", "2 / 4") */
    case GRID_COLUMN = 'grid_column';

    /** Explicitní grid-row hodnota bloku (např. "1 / -1", "2 / 4") */
    case GRID_ROW = 'grid_row';

    /** Custom grid-template-columns pro sekci (např. "auto auto 1fr") */
    case COLUMN_TEMPLATE = 'column_template';

    // =========================================================================
    // BLOCK KEYS - klíče pro bloky (form elementy)
    // =========================================================================

    /** Typ komponenty (input, select, textarea) */
    case COMPONENT = 'component';

    /** Varianta komponenty (text, email, radio, checkbox) */
    case VARIANT = 'variant';

    /** Pravidla validace a chování */
    case RULES = 'rules';

    /** Možnosti pro select/radio */
    case OPTIONS = 'options';

    /** Skupina radio buttonů */
    case GROUP = 'group';

    /** Meta atributy (data-* atributy) */
    case META = 'meta';

    /** Tooltip/nápověda */
    case TOOLTIP = 'tooltip';

    /** AI klíč pro mapování */
    case AI_KEY = 'ai_key';

    // =========================================================================
    // RULES KEYS - klíče uvnitř pravidel
    // =========================================================================

    /** Výchozí hodnota */
    case DEFAULT = 'default';

    /** Povinnost vyplnění */
    case REQUIRED = 'required';

    /** Deaktivace pole */
    case DISABLED = 'disabled';

    /** Podmíněná viditelnost bloku */
    case VISIBLE_IF = 'visible_if';

    // =========================================================================
    // GENERATOR KEYS - klíče specifické pro generátory
    // =========================================================================

    /** Konfigurace generátoru */
    case CONFIG = 'config';

    /** Rozřešený layout (interní) */
    case RESOLVED_LAYOUT = '_resolved_layout';
}
