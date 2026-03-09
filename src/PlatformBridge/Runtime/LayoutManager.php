<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Runtime;

use Zoom\PlatformBridge\Config\ConfigKeys;

/**
 * Pomocná třída pro zpracování atributů layoutu (sloupce, span, pozice v gridu) z definic sekcí a bloků.
 *
 * Odpovědnosti:
 * - Vytváření data-* atributů pro sekce (grid kontejnery), včetně column_template
 * - Vytváření data-* atributů pro bloky (grid položky), včetně grid_column/grid_row
 * - Obalování bloků do layout kontejnerů se správnými data atributy
 *
 * Doporučené postupy:
 * - Při přidávání nových layout funkcí rozšiř ConfigKeys a odpovídajícím způsobem uprav buildování atributů.
 *
 * @see Zoom\PlatformBridge\Config\ConfigKeys
 *
 * @package PlatformBridge\Runtime
 */
final class LayoutManager
{
    /** Prefix pro layout data atributy */
    private const ATTR_PREFIX = 'data-layout';

    /**
     * Sestaví data-* atributy pro grid item bloku.
     *
     * Tyto atributy používá layout renderer pro výpočet
     * pozice a velikosti bloku v gridu.
     *
     * Zpracovává:
     * - span
     * - row-span
     * - grid-column
     * - grid-row
     *
     * @param array $blockDef Definice bloku z layoutu (ref, span apod.)
     * @return array Asociativní pole data atributů např. ['data-layout-span' => '2']
     */
    private static function buildBlockAttributes(array $blockDef): array
    {
        $attrs = [];

        $span = $blockDef[ConfigKeys::SPAN->value] ?? null;
        if ($span !== null) {
            $attrs[self::ATTR_PREFIX . '-span'] = (string) $span;
        }

        $rowSpan = $blockDef[ConfigKeys::ROW_SPAN->value] ?? null;
        if ($rowSpan !== null) {
            $attrs[self::ATTR_PREFIX . '-row-span'] = (string) $rowSpan;
        }

        // Explicitní grid-column (např. "1 / -1", "2 / 4")
        $gridColumn = $blockDef[ConfigKeys::GRID_COLUMN->value] ?? null;
        if ($gridColumn !== null) {
            $attrs[self::ATTR_PREFIX . '-grid-column'] = $gridColumn;
        }

        // Explicitní grid-row (např. "1 / -1", "2 / 4")
        $gridRow = $blockDef[ConfigKeys::GRID_ROW->value] ?? null;
        if ($gridRow !== null) {
            $attrs[self::ATTR_PREFIX . '-grid-row'] = $gridRow;
        }

        return $attrs;
    }

    /**
     * Obalí HTML bloku do layout wrapperu s data atributy.
     *
     * Wrapper sjednocuje všechny elementy bloku (včetně radio skupiny)
     * pod jeden root element s data-block-id.
     *
     * @param string $blockHtml Renderovaný HTML bloku (včetně všech jeho elementů)
     * @param array $blockDef Definice bloku z layoutu
     * @param string $blockId = '' ID bloku (např. "topic_source", "email_topic")
     *
     * @return string HTML s wrapperem
     *
     * @see buildBlockAttributes()
     * @see buildVisibilityAttribute()
     *
     * @internal
     */
    public static function wrapBlock(string $blockHtml, array $blockDef, string $blockId = ''): string
    {
        $attrs = self::buildBlockAttributes($blockDef);
        $visibilityAttr = self::buildVisibilityAttribute($blockDef);
        $attrs = array_merge($attrs, $visibilityAttr);

        if (empty($attrs)) {
            return sprintf(
                '<div class="ai-module__block" data-block-id="%s">%s</div>',
                htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8'),
                $blockHtml
            );
        }

        $attrString = self::renderAttributes($attrs);

        return sprintf(
            '<div class="ai-module__block" data-block-id="%s" %s>%s</div>',
            htmlspecialchars($blockId, ENT_QUOTES, 'UTF-8'),
            $attrString,
            $blockHtml
        );
    }

    /**
     * Sestaví data-visible-if atribut z pravidel bloku.
     *
     * Čte rules.visible_if z konfigurace bloku a převádí ho na JSON-encoded
     * data atribut. Podporuje podmínky pro radio a checkbox pole.
     *
     * Příklad v blocks.json:
     *   "rules": { "visible_if": { "topic_source": "custom" } }
     *
     * @param array $blockDef Definice bloku (rozřešená, včetně rules)
     * @return array Asociativní pole s data atributem, nebo prázdné pole
     */
    private static function buildVisibilityAttribute(array $blockDef): array
    {
        $visibleIf = $blockDef[ConfigKeys::RULES->value][ConfigKeys::VISIBLE_IF->value] ?? null;

        if ($visibleIf === null || !is_array($visibleIf) || empty($visibleIf)) {
            return [];
        }

        return [
            'data-visible-if' => json_encode($visibleIf, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * Převede asociativní pole atributů na HTML string.
     *
     * @param array $attrs ['data-layout-span' => '2']
     * @return string 'data-layout-span="2"'
     */
    private static function renderAttributes(array $attrs): string
    {
        $pairs = [];
        foreach ($attrs as $key => $value) {
            $pairs[] = sprintf(
                '%s="%s"',
                htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            );
        }

        return implode(' ', $pairs);
    }
}
