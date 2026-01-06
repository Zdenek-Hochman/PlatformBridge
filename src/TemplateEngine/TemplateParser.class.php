<?php

namespace TemplateEngine;

use Translator\Translator;

/**
 * Třída TemplateParser slouží k parsování šablon a modifikaci proměnných.
 */
class TemplateParser extends VariableModifier
{
    /**
     * Třída TemplateParser
     *
     * Tato třída obsahuje statické pole $tags, které definuje různé šablony tagů pro parsování šablon.
     * Každý tag je definován jako klíč-vyhledávací-výraz páru, který se používá k nalezení a nahrazení tagu ve šabloně.
     * Pole $tags obsahuje následující tagy:
     *
     * - 'for': Tag pro iteraci přes pole. Vyhledává výrazy ve tvaru {for $array as $variable on $index}.
     * - 'for_close': Tag pro ukončení iterace přes pole. Vyhledává výrazy ve tvaru {/for}.
     * - 'if': Tag pro podmíněné zobrazení obsahu. Vyhledává výrazy ve tvaru {if "function|$variable" or "$variable == 'value'"}
     * - 'elseif': Tag pro další podmíněné zobrazení obsahu. Vyhledává výrazy ve tvaru {elseif "function|$variable" or "$variable == 'value'"}
     * - 'else': Tag pro zobrazení obsahu v případě, že žádná předchozí podmínka neplatí. Vyhledává výrazy ve tvaru {else}.
     * - 'if_close': Tag pro ukončení podmíněného zobrazení obsahu. Vyhledává výrazy ve tvaru {/if}.
     * - 'require': Tag pro vložení obsahu z jiného souboru. Vyhledává výrazy ve tvaru {_require file}.
     * - 'function': Tag pro volání uživatelské funkce. Vyhledává výrazy ve tvaru {function="functionName(arguments)"}.
     * - 'variable': Tag pro zobrazení hodnoty proměnné. Vyhledává výrazy ve tvaru {$variable}.
     * - 'constant': Tag pro zobrazení hodnoty konstanty. Vyhledává výrazy ve tvaru {% constantName %}.
     * - 'translate': Tag pro překlad textu. Vyhledává výrazy ve tvaru {_tran k='key' d='default' l='lang'}.
     *
     * @package TemplateEngine
     */
    protected static $tags = [
        'for' => array(
            '({for.*?})',
            '/{for\s*(?<array>\$\w+)\s+as\s*(?<variable>\$\w+)(?:\s+on\s*(?<index>\$\w+))?}/'
        ),
        'for_close' => array('({\/for})', '/{\/for}/'),
        'if' => array(
            '({if.*?})',
            // Podporuje zápis: {if $var}, {if $var == "x"}, {if "...|..."}
            '/{if\s*(?:"(?<function>\$[\w\.]+\|\w+)"|(?<variable>\$[\w\.]+)\s*(?<operator>==|!=)\s*"(?<value>.*)"|(?<simple>\$[\w\.]+))}/'
        ),
        'elseif' => array(
            '({elseif.*?})',
            '/{elseif\s*(?:"(?<function>\$[\w\.]+\|\w+)"|(?<variable>\$[\w\.]+)\s*(?<operator>==|!=)\s*"(?<value>.*)"|(?<simple>\$[\w\.]+))}/'
        ),
        'else' => array('({else})', '/{else}/'),
        'if_close' => array('({\/if})', '/{\/if}/'),
        'require' => array('({_require.*?})', '/{\_require\s+(?<file>[^.]+).*}/'),
        'function' => array(
            '({function.*?})',
            '/{function="([a-zA-Z_][a-zA-Z_0-9\:]*)(\(.*\)){0,1}"}/'
        ),
        'variable' => array('({(?:raw\s+)?\$.*?})', '/{((?:raw\s+)?\$.*?)}/'),
        'constant' => array('({\%.*?})', '/{\%\s*(\w+)\s*\%}/'),
        'translate' => array(
            '({_tran.*?})',
            '/{_tran\s{0,1}k=\'(?<key>\${0,1}[^\']+)\'\s{0,1}d=\'(?<default>[^\']+)\'(\s*l=\'(?<lang>[a-zA-Z]+)\')?}/'
        ),
    ];

    protected static $blackList = [
        'exec',
        'shell_exec',
        'pcntl_exec',
        'passthru',
        'proc_open',
        'system',
        'posix_kill',
        'posix_setsid',
        'pcntl_fork',
        'posix_uname',
        'php_uname',
        'phpinfo',
        'popen',
        'file_get_contents',
        'file_put_contents',
        'rmdir',
        'mkdir',
        'unlink',
        'highlight_contents',
        'symlink',
        'apache_child_terminate',
        'apache_setenv',
        'define_syslog_variables',
        'escapeshellarg',
        'escapeshellcmd',
        'eval',
        'fp',
        'fput',
        'ftp_connect',
        'ftp_exec',
        'ftp_get',
        'ftp_login',
        'ftp_nb_fput',
        'ftp_put',
        'ftp_raw',
        'ftp_rawlist',
        'highlight_file',
        'ini_alter',
        'ini_get_all',
        'ini_restore',
        'inject_code',
        'mysql_pconnect',
        'openlog',
        'passthru',
        'php_uname',
        'phpAds_remoteInfo',
        'phpAds_XmlRpc',
        'phpAds_xmlrpcDecode',
        'phpAds_xmlrpcEncode',
        'posix_getpwuid',
        'posix_kill',
        'posix_mkfifo',
        'posix_setpgid',
        'posix_setsid',
        'posix_setuid',
        'posix_uname',
        'proc_close',
        'proc_get_status',
        'proc_nice',
        'proc_open',
        'proc_terminate',
        'syslog',
        'xmlrpc_entity_decode'
    ];

    protected static $registered_tags = [];
    protected $config = [];
    protected $tagSplit = [];
    protected $tagMatch = [];

    private $parsedCode = '';

    public function __construct(array $config)
    {
        $this->config = $config;

        $splits = array_column(static::$tags, 0);
        $matches = array_column(static::$tags, 1);

        // Vytvoří asociativní pole $tagSplit, kde klíče jsou indexy z static::$tags a hodnoty jsou $splits.
        $this->tagSplit = array_combine(array_keys(static::$tags), $splits);
        // Vytvoří asociativní pole $tagMatch, kde klíče jsou indexy z static::$tags a hodnoty jsou $matches.
        $this->tagMatch = array_combine(array_keys(static::$tags), $matches);

        $keys = array_keys(static::$registered_tags);
        $this->tagSplit += array_merge($this->tagSplit, $keys);
    }

    /**
     * Metoda parseCodeToPhp() slouží k převodu kódu do PHP syntaxe.
     * Převede zadaný šablonový kód na PHP kód.
     *
     * Tato metoda provádí následující kroky:
     * 1. Odstraní komentáře ze šablonového kódu, pokud je to povoleno.
     * 2. Rozdělí šablonový kód na části podle definovaných tagů.
     * 3. Zpracovává HTML části kódu a vytváří z nich PHP kód.
     * 4. Odstraní nadbytečné mezery a vrátí finální PHP kód.
     *
     * @param string $code Kód, který má být převeden na PHP syntaxi.
     * @return string Převedený kód bez komentářů a s odstraněnými bílými znaky.
     */
    public function parseCodeToPhp(string $code): string
    {
        // Odstraní komentáře
        if ($this->config['remove_comments']) {
            $code = preg_replace('/{\*(.*)\*}/Uis', '', $code);
        }

        // Rekurzivní parsování bloků, dokud v kódu zůstávají tagy
        $prevCode = null;
        $parsed = '';
        do {
            $this->parsedCode = '';
            $prevCode = $code;
            $codeSplit = preg_split("/" . implode("|", $this->tagSplit) . "/", $code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if ($codeSplit) {
                foreach ($codeSplit as $html) {
                    $this->processHtml($html);
                }
                $parsed = str_replace('?><?php', ' ', $this->parsedCode);
            }
            $code = $parsed;
        } while ($code !== $prevCode && preg_match('/{(for|if|elseif|else|\/for|\/if)[^}]*}/', $code));

        // Odstraní všechny nadbytečné mezery (mezery, tabulátory, nové řádky) z konečného PHP kódu.
        if ($this->config['debug'] === false) {
            $code = preg_replace('/[\r\n\t]+/', '', $code);
        }

        return $code;
    }

    /**
     * Zpracovává HTML obsah a převádí ho na PHP kód na základě tagů.
     *
     * Tato metoda prochází HTML obsah a podle definovaných tagů a jejich vzorců provádí odpovídající akce.
     * Tagy se zpracovávají a přidávají do konečného PHP kódu.
     *
     * @param string $html HTML obsah, který se má zpracovat.
     * @return void
     */
    public function processHtml(string $html): void
    {
        foreach ($this->tagMatch as $tag => $pattern) {

            if (preg_match($pattern, $html, $matches)) {
                switch ($tag) {
                    // TODO: Implementovat zpracování html tagů (Vypnput htmlspecialchars)
                    case 'variable':
                        $this->appendToParsedCode(parent::transformHtmlVariables($matches[1], true, true) . ";");
                        return;
                    case 'if':
                    case 'elseif':
                        $this->appendToParsedCode($this->processCondition($tag, $matches));
                        return;
                    case 'for':
                        $this->appendToParsedCode($this->processForLoop($matches));
                        return;
                    case 'if_close':
                    case 'for_close':
                        $this->appendToParsedCode("}");
                        return;
                    case 'require':
                        $this->appendToParsedCode('require $this->getAndUpdateTemplateCache("' . parent::transformHtmlVariables($matches[1], false, false) . '");');
                        return;
                    case 'constant':
                        $this->appendToParsedCode(parent::replaceConstantModifier($matches[1]));
                        return;
                    case 'translate':
                        $this->appendToParsedCode($this->processTranslate($matches));
                        return;
                }
            }
        }

        // Pokud žádný tag neodpovídá, přidá HTML obsah přímo do zpracovaného kódu
        // echo "<pre>";
        // var_dump((new StyleGenerator())->collectStylesFromDB(["text", "text2", "background", "background2"]));
        // echo "</pre>";

        $this->parsedCode .= $html;
    }

    /**
     * Přidá kód do analyzovaného kódu.
     *
     * @param string $code Kód, který se má přidat.
     * @return void
     */
    public function appendToParsedCode(string $code): void
    {
        $this->parsedCode .= parent::addPhpWrap($code);
    }

    /**
     * Metoda processTranslate zpracovává překlad textu.
     *
     * @param array $matches Pole s výsledky regulárního výrazu.
     * @return string Výsledný PHP kód pro výpis přeloženého textu.
     */
    private function processTranslate(array $matches): string
    {
        $key = parent::transformHtmlVariables($matches["key"] ?? "", false, false);

        return "echo " . json_encode(Translator::fetchTranslations($key, $matches["lang"], $matches["default"]), JSON_UNESCAPED_UNICODE) . ";";
    }

    /**
     * Metoda processForLoop zpracovává výskyty for smyček v šabloně.
     *
     * @param array $matches Pole s výskytem for smyčky a jejími parametry.
     * @return string Vygenerovaný kód pro for smyčku.
     */
    private function processForLoop(array $matches): string
    {
        [, $array, $key] = $matches;

        $parameter = parent::transformHtmlVariables($key, false, false);

        $loopCode = isset($matches[3]) ? "foreach($array as $matches[3] => $parameter){" : "foreach($array as $parameter){";

        return $loopCode;
    }

    /**
     * Metoda processCondition zpracovává podmínky v šabloně.
     *
     * @param string $type Typ podmínky ('if' nebo 'elseif').
     * @param array $matches Pole s výsledky vyhledávání v šabloně.
     * @return string Výsledný řetězec s podmínkou.
     */
    private function processCondition(string $type, array $matches): string
    {
        if (!empty($matches["function"])) {
            $final = parent::transformHtmlVariables($matches["function"], false, false);
        } elseif (!empty($matches["operator"])) {
            $final = $matches["variable"] . $matches["operator"] . json_encode($matches["value"]);
        } elseif (!empty($matches["simple"])) {
            $final = parent::transformHtmlVariables($matches["simple"], false, false);
        } else {
            $final = '';
        }
        return $type === 'if' ? "if( $final ){" : "}elseif( $final ){";
    }
}
