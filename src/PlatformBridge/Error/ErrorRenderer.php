<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Error;

/**
 * Whoops-style error renderer s interaktivním stack trace, náhledem zdrojového kódu
 * a informacemi o prostředí.
 *
 * Rozlišuje dva režimy:
 *  1. **Detailní** (dev) – plně interaktivní error stránka (sidebar + code preview + env).
 *  2. **Produkční** – pouze obecné hlášení bez technických detailů.
 *
 * Výstup je zcela self-contained (inline CSS + JS), bez externích závislostí.
 */
final class ErrorRenderer
{
    /** Kolik řádků kódu zobrazit nad/pod chybou */
    private const CONTEXT_LINES = 12;

    public function __construct(
        private readonly bool $showDetails = true,
    ) {}

    // ─── Veřejné API ─────────────────────────────────────────────────────

    public function render(\Throwable $e): void
    {
        if (!$this->showDetails) {
            $this->renderGeneric();
            return;
        }

        $this->renderWhoops($e);
    }

    // ─── Produkční výstup ────────────────────────────────────────────────

    private function renderGeneric(): void
    {
        echo <<<'HTML'
        <!DOCTYPE html>
        <html lang="cs"><head><meta charset="utf-8"><title>Chyba</title></head><body style="margin:0;background:#0e1117;display:flex;align-items:center;justify-content:center;min-height:100vh">
        <div style="background:#161b22;color:#c9d1d9;padding:32px 40px;border-radius:12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:480px;text-align:center;border:1px solid #30363d">
            <div style="font-size:48px;margin-bottom:16px">⚠️</div>
            <h2 style="margin:0 0 12px 0;color:#f85149;font-size:20px">Něco se pokazilo</h2>
            <p style="margin:0;color:#8b949e;line-height:1.5">Problém byl zaznamenán. Pokud potíže přetrvávají, kontaktuj administrátora.</p>
        </div>
        </body></html>
        HTML;
    }

    // ─── Whoops-style interaktivní výstup ────────────────────────────────

    private function renderWhoops(\Throwable $e): void
    {
        $frames   = $this->buildFrames($e);
        $framesJs = json_encode($frames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        // Header info
        $exceptionClass = $this->esc(get_class($e));
        $message        = $this->esc($e->getMessage());

        // Severity badge for ErrorException
        $severityBadge = '';
        if ($e instanceof \ErrorException) {
            $label = $this->esc($this->severityLabel($e->getSeverity()));
            $severityBadge = "<span class=\"wh-badge\">{$label}</span> ";
        }

        // RenderableException extras
        $titleHtml   = '';
        $hintHtml    = '';
        $contextHtml = '';
        if ($e instanceof RenderableException) {
            $titleHtml = '<div class="wh-app-title">' . $this->esc($e->getTitle()) . '</div>';
            if ($e->getHint() !== null) {
                $hintHtml = '<div class="wh-hint"><span class="wh-hint-icon">💡</span> <strong>Tip:</strong> ' . $this->esc($e->getHint()) . '</div>';
            }
            $ctx = $e->getRenderContext();
            if (!empty($ctx)) {
                $contextHtml = '<div class="wh-context-pairs">';
                foreach ($ctx as $label => $value) {
                    $contextHtml .= '<span class="wh-ctx-label">' . $this->esc($label) . ':</span> <span class="wh-ctx-value">' . $this->esc($value) . '</span><br>';
                }
                $contextHtml .= '</div>';
            }
        }

        // Environment tables
        $envHtml     = $this->buildEnvTable();
        $requestHtml = $this->buildRequestTable();
        $headersHtml = $this->buildHeadersTable();

        echo <<<HTML
        <!DOCTYPE html>
        <html lang="cs">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$exceptionClass} – Error</title>
        <style>{$this->getStyles()}</style>
        </head>
        <body>
        <!-- ─── Header ─────────────────────────────────────────────── -->
        <header class="wh-header">
            <div class="wh-header-inner">
                {$titleHtml}
                <div class="wh-exc-class">{$severityBadge}{$exceptionClass}</div>
                <div class="wh-exc-msg">{$message}</div>
                {$hintHtml}
                {$contextHtml}
            </div>
        </header>

        <!-- ─── Main layout ────────────────────────────────────────── -->
        <div class="wh-main">
            <!-- Sidebar: stack frames -->
            <aside class="wh-sidebar" id="whSidebar"></aside>

            <!-- Content: source code + env -->
            <section class="wh-content">
                <div class="wh-code-header" id="whCodeHeader"></div>
                <div class="wh-code-wrap" id="whCodeWrap">
                    <table class="wh-code" id="whCode"></table>
                </div>

                <!-- Collapsible environment sections -->
                <div class="wh-env-sections">
                    {$this->envSection('Prostředí', 'env', $envHtml)}
                    {$this->envSection('Request', 'request', $requestHtml)}
                    {$this->envSection('Hlavičky', 'headers', $headersHtml)}
                </div>
            </section>
        </div>

        <script>
        (function(){
            var frames = {$framesJs};
            var sidebar = document.getElementById('whSidebar');
            var codeHeader = document.getElementById('whCodeHeader');
            var codeTable = document.getElementById('whCode');
            var codeWrap = document.getElementById('whCodeWrap');
            var activeIdx = 0;

            function esc(s) {
                var d = document.createElement('div');
                d.appendChild(document.createTextNode(s));
                return d.innerHTML;
            }

            function renderSidebar() {
                var html = '';
                for (var i = 0; i < frames.length; i++) {
                    var f = frames[i];
                    var cls = (i === activeIdx) ? 'wh-frame active' : 'wh-frame';
                    var call = f.call ? esc(f.call) : '<span class="wh-frame-anon">{closure}</span>';
                    html += '<div class="' + cls + '" data-idx="' + i + '">'
                         +  '<div class="wh-frame-idx">' + i + '</div>'
                         +  '<div class="wh-frame-info">'
                         +    '<div class="wh-frame-call">' + call + '</div>'
                         +    '<div class="wh-frame-file">' + esc(f.shortFile) + ':' + f.line + '</div>'
                         +  '</div>'
                         + '</div>';
                }
                sidebar.innerHTML = html;

                var items = sidebar.querySelectorAll('.wh-frame');
                for (var j = 0; j < items.length; j++) {
                    items[j].addEventListener('click', function() {
                        selectFrame(parseInt(this.getAttribute('data-idx'), 10));
                    });
                }
            }

            function renderCode(idx) {
                var f = frames[idx];
                codeHeader.innerHTML = '<span class="wh-code-file">' + esc(f.file) + '</span>'
                    + '<span class="wh-code-line">řádek ' + f.line + '</span>';

                if (!f.source || f.source.length === 0) {
                    codeTable.innerHTML = '<tr><td class="wh-ln"></td><td class="wh-code-td wh-no-source">Zdrojový kód není dostupný</td></tr>';
                    return;
                }

                var html = '';
                for (var i = 0; i < f.source.length; i++) {
                    var s = f.source[i];
                    var cls = s.highlight ? ' class="wh-highlight"' : '';
                    html += '<tr' + cls + '>'
                         +  '<td class="wh-ln">' + s.num + '</td>'
                         +  '<td class="wh-code-td">' + esc(s.code) + '</td>'
                         + '</tr>';
                }
                codeTable.innerHTML = html;

                // Scroll to highlighted line
                var hl = codeWrap.querySelector('.wh-highlight');
                if (hl) {
                    hl.scrollIntoView({block: 'center', behavior: 'instant'});
                }
            }

            function selectFrame(idx) {
                activeIdx = idx;
                renderSidebar();
                renderCode(idx);
            }

            // Collapsible sections
            var toggles = document.querySelectorAll('.wh-env-toggle');
            for (var t = 0; t < toggles.length; t++) {
                toggles[t].addEventListener('click', function() {
                    var body = this.nextElementSibling;
                    var open = body.style.display !== 'none';
                    body.style.display = open ? 'none' : 'block';
                    this.querySelector('.wh-env-arrow').textContent = open ? '▶' : '▼';
                });
            }

            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp' || e.key === 'k') {
                    e.preventDefault();
                    selectFrame(Math.max(0, activeIdx - 1));
                } else if (e.key === 'ArrowDown' || e.key === 'j') {
                    e.preventDefault();
                    selectFrame(Math.min(frames.length - 1, activeIdx + 1));
                }
            });

            // Init
            if (frames.length > 0) {
                selectFrame(0);
            }
        })();
        </script>
        </body>
        </html>
        HTML;
    }

    // ─── Frame building ──────────────────────────────────────────────────

    /**
     * Sestaví pole framů pro JS – každý frame obsahuje soubor, řádek,
     * volání a úryvek zdrojového kódu.
     *
     * @return list<array{file: string, shortFile: string, line: int, call: string, source: list<array{num: int, code: string, highlight: bool}>}>
     */
    private function buildFrames(\Throwable $e): array
    {
        $frames = [];

        // Frame 0 = místo, kde výjimka vznikla
        $frames[] = [
            'file'      => $e->getFile(),
            'shortFile' => $this->shortenPath($e->getFile()),
            'line'      => $e->getLine(),
            'call'      => get_class($e),
            'source'    => $this->readSource($e->getFile(), $e->getLine()),
        ];

        foreach ($e->getTrace() as $t) {
            $file     = $t['file'] ?? '[internal]';
            $line     = $t['line'] ?? 0;
            $class    = $t['class'] ?? '';
            $type     = $t['type'] ?? '';
            $function = $t['function'] ?? '';
            $call     = $class . $type . $function . '()';

            $frames[] = [
                'file'      => $file,
                'shortFile' => $this->shortenPath($file),
                'line'      => (int) $line,
                'call'      => $call,
                'source'    => $file !== '[internal]' ? $this->readSource($file, (int) $line) : [],
            ];
        }

        return $frames;
    }

    /**
     * Načte úryvek zdrojového kódu kolem zadaného řádku.
     *
     * @return list<array{num: int, code: string, highlight: bool}>
     */
    private function readSource(string $file, int $line): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = @file($file);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $line - self::CONTEXT_LINES - 1);
        $end   = min(count($lines), $line + self::CONTEXT_LINES);

        $result = [];
        for ($i = $start; $i < $end; $i++) {
            $result[] = [
                'num'       => $i + 1,
                'code'      => rtrim($lines[$i], "\r\n"),
                'highlight' => ($i + 1) === $line,
            ];
        }

        return $result;
    }

    /**
     * Zkrátí cestu k souboru – zachová posledních N segmentů.
     */
    private function shortenPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $parts      = explode('/', $normalized);

        return count($parts) > 3
            ? '…/' . implode('/', array_slice($parts, -3))
            : $normalized;
    }

    // ─── Environment tables ──────────────────────────────────────────────

    private function buildEnvTable(): string
    {
        $rows = [
            'PHP verze'       => PHP_VERSION,
            'PHP SAPI'        => PHP_SAPI,
            'Systém'          => PHP_OS . ' (' . php_uname('r') . ')',
            'Paměť (peak)'    => $this->formatBytes(memory_get_peak_usage(true)),
            'Paměť (aktuální)' => $this->formatBytes(memory_get_usage(true)),
            'Zend Engine'     => zend_version(),
            'Extensions'      => implode(', ', array_slice(get_loaded_extensions(), 0, 20)) . (count(get_loaded_extensions()) > 20 ? '…' : ''),
        ];

        return $this->keyValueTable($rows);
    }

    private function buildRequestTable(): string
    {
        if (PHP_SAPI === 'cli') {
            return '<div class="wh-env-empty">CLI prostředí – žádný HTTP request</div>';
        }

        $rows = [];
        $rows['Metoda']     = $_SERVER['REQUEST_METHOD'] ?? '-';
        $rows['URI']        = $_SERVER['REQUEST_URI'] ?? '-';
        $rows['Host']       = $_SERVER['HTTP_HOST'] ?? '-';
        $rows['Protokol']   = $_SERVER['SERVER_PROTOCOL'] ?? '-';
        $rows['Vzdálená IP'] = $_SERVER['REMOTE_ADDR'] ?? '-';

        if (!empty($_GET))  { $rows['$_GET']  = $this->esc($this->dumpCompact($_GET)); }
        if (!empty($_POST)) { $rows['$_POST'] = $this->esc($this->dumpCompact($_POST)); }

        return $this->keyValueTable($rows);
    }

    private function buildHeadersTable(): string
    {
        if (PHP_SAPI === 'cli') {
            return '<div class="wh-env-empty">CLI prostředí – žádné hlavičky</div>';
        }

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', substr($k, 5));
                $headers[$name] = $v;
            }
        }

        if (empty($headers)) {
            return '<div class="wh-env-empty">Žádné hlavičky</div>';
        }

        return $this->keyValueTable($headers);
    }

    private function keyValueTable(array $rows): string
    {
        $html = '<table class="wh-env-table">';
        foreach ($rows as $key => $value) {
            $html .= '<tr><td class="wh-env-key">' . $this->esc($key) . '</td>'
                   . '<td class="wh-env-val">' . $this->esc((string) $value) . '</td></tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function envSection(string $title, string $id, string $content): string
    {
        return <<<HTML
        <div class="wh-env-section" id="whEnv-{$id}">
            <div class="wh-env-toggle">
                <span class="wh-env-arrow">▼</span> {$this->esc($title)}
            </div>
            <div class="wh-env-body">{$content}</div>
        </div>
        HTML;
    }

    // ─── Pomocné metody ──────────────────────────────────────────────────

    private function severityLabel(int $severity): string
    {
        return match ($severity) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR       => 'Fatal Error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'Warning',
            E_NOTICE, E_USER_NOTICE                                      => 'Notice',
            E_STRICT                                                     => 'Strict',
            E_DEPRECATED, E_USER_DEPRECATED                              => 'Deprecated',
            E_PARSE                                                      => 'Parse Error',
            default                                                      => "Unknown ({$severity})",
        };
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $val = (float) $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }
        return round($val, 2) . ' ' . $units[$i];
    }

    private function dumpCompact(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: var_export($value, true);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ─── Inline CSS ──────────────────────────────────────────────────────

    private function getStyles(): string
    {
        return <<<'CSS'
        /* ── Reset & Base ───────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 14px; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0e1117; color: #c9d1d9; line-height: 1.5;
            min-height: 100vh; display: flex; flex-direction: column;
        }

        /* ── Header ─────────────────────────────────────────────── */
        .wh-header {
            background: linear-gradient(135deg, #1a0a0a 0%, #2d1117 50%, #1a0a0a 100%);
            border-bottom: 3px solid #f8514930;
            padding: 24px 0;
        }
        .wh-header-inner {
            max-width: 1600px; margin: 0 auto; padding: 0 24px;
        }
        .wh-app-title {
            font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px;
            color: #f0883e; margin-bottom: 6px; font-weight: 600;
        }
        .wh-exc-class {
            font-size: 22px; font-weight: 700; color: #f85149;
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
            word-break: break-word;
        }
        .wh-exc-msg {
            font-size: 16px; color: #e6edf3; margin-top: 8px; line-height: 1.6;
            word-break: break-word;
        }
        .wh-badge {
            display: inline-block; background: #da3633; color: #fff;
            font-size: 11px; padding: 2px 8px; border-radius: 10px;
            text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;
            vertical-align: middle; margin-right: 8px;
        }
        .wh-hint {
            margin-top: 12px; padding: 10px 14px; background: #0d1d30;
            border-left: 3px solid #58a6ff; border-radius: 6px;
            color: #a5d6ff; font-size: 13px;
        }
        .wh-hint-icon { margin-right: 4px; }
        .wh-context-pairs {
            margin-top: 10px; font-size: 13px; color: #8b949e;
        }
        .wh-ctx-label { color: #7ee787; font-weight: 600; }
        .wh-ctx-value { color: #c9d1d9; }

        /* ── Main Layout ────────────────────────────────────────── */
        .wh-main {
            display: flex; flex: 1; max-width: 1600px;
            margin: 0 auto; width: 100%;
        }

        /* ── Sidebar (Stack Frames) ─────────────────────────────── */
        .wh-sidebar {
            width: 360px; min-width: 280px; background: #0d1117;
            border-right: 1px solid #21262d; overflow-y: auto;
            max-height: calc(100vh - 150px);
            flex-shrink: 0;
        }
        .wh-frame {
            display: flex; gap: 10px; padding: 10px 14px;
            border-bottom: 1px solid #161b22; cursor: pointer;
            transition: background .15s;
        }
        .wh-frame:hover { background: #161b22; }
        .wh-frame.active {
            background: #1c2333; border-left: 3px solid #58a6ff;
            padding-left: 11px;
        }
        .wh-frame-idx {
            color: #484f58; font-size: 12px; font-weight: 700;
            min-width: 22px; text-align: right; padding-top: 2px;
            font-family: 'JetBrains Mono', Consolas, monospace;
        }
        .wh-frame-info { overflow: hidden; }
        .wh-frame-call {
            font-size: 13px; font-weight: 600; color: #e6edf3;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            font-family: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
        }
        .wh-frame-anon { color: #8b949e; font-style: italic; }
        .wh-frame-file {
            font-size: 11px; color: #6e7681; margin-top: 2px;
            font-family: 'JetBrains Mono', Consolas, monospace;
        }

        /* ── Content (Code + Env) ───────────────────────────────── */
        .wh-content { flex: 1; overflow: hidden; display: flex; flex-direction: column; }
        .wh-code-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px; background: #161b22; border-bottom: 1px solid #21262d;
            font-family: 'JetBrains Mono', Consolas, monospace; font-size: 12px;
        }
        .wh-code-file { color: #79c0ff; }
        .wh-code-line { color: #8b949e; }

        .wh-code-wrap {
            overflow: auto; max-height: 50vh; background: #0d1117;
            flex-shrink: 0;
        }
        .wh-code {
            width: 100%; border-collapse: collapse;
            font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', Consolas, 'Courier New', monospace;
            font-size: 13px; line-height: 1.65; tab-size: 4;
        }
        .wh-code tr { transition: background .1s; }
        .wh-code tr:hover { background: #161b2244; }
        .wh-code tr.wh-highlight {
            background: #3b2e0a !important;
        }
        .wh-code tr.wh-highlight td { border-top: 1px solid #d29922; border-bottom: 1px solid #d29922; }
        .wh-ln {
            width: 56px; min-width: 56px; text-align: right; padding: 0 12px 0 0;
            color: #484f58; user-select: none; vertical-align: top;
            border-right: 1px solid #21262d;
        }
        .wh-highlight .wh-ln { color: #d29922; font-weight: 700; border-right-color: #d29922; }
        .wh-code-td { padding: 0 16px; white-space: pre; color: #c9d1d9; }
        .wh-no-source { color: #6e7681; font-style: italic; padding: 24px 16px; }

        /* ── Environment Sections ───────────────────────────────── */
        .wh-env-sections { border-top: 1px solid #21262d; overflow-y: auto; }
        .wh-env-section { border-bottom: 1px solid #21262d; }
        .wh-env-toggle {
            padding: 10px 16px; background: #161b22; cursor: pointer;
            font-weight: 600; font-size: 13px; color: #8b949e;
            user-select: none; transition: background .15s;
        }
        .wh-env-toggle:hover { background: #1c2128; color: #c9d1d9; }
        .wh-env-arrow { display: inline-block; width: 16px; font-size: 10px; }
        .wh-env-body { background: #0d1117; padding: 0; }
        .wh-env-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .wh-env-table tr { border-bottom: 1px solid #161b22; }
        .wh-env-table tr:hover { background: #161b2244; }
        .wh-env-key {
            padding: 6px 14px; color: #7ee787; white-space: nowrap;
            font-family: 'JetBrains Mono', Consolas, monospace; width: 200px;
            vertical-align: top; font-weight: 500;
        }
        .wh-env-val {
            padding: 6px 14px; color: #c9d1d9; word-break: break-all;
            font-family: 'JetBrains Mono', Consolas, monospace;
        }
        .wh-env-empty {
            padding: 14px 16px; color: #6e7681; font-style: italic; font-size: 13px;
        }

        /* ── Responsive ─────────────────────────────────────────── */
        @media (max-width: 900px) {
            .wh-main { flex-direction: column; }
            .wh-sidebar {
                width: 100%; max-height: 240px;
                border-right: none; border-bottom: 1px solid #21262d;
            }
        }

        /* ── Scrollbar ──────────────────────────────────────────── */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0d1117; }
        ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #484f58; }
        CSS;
    }
}
