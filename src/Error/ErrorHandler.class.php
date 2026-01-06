<?php

namespace Error;

final class ErrorHandler
{
    private bool $showDetails;

    public function __construct(bool $showDetails = true)
    {
        $this->showDetails = $showDetails;
    }

    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);

        error_reporting(E_ALL);
        ini_set('display_errors', '0');
    }

    public function handleException(\Throwable $e): void
    {
        http_response_code(500);

        if ($this->showDetails) {
            $this->renderHtml($e);
        } else {
            $this->renderGeneric();
        }
    }

    public function handleError(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
    {
        // Převést PHP error na výjimku, aby se jednotně zpracovalo
        $errfile = $errfile ?? '[internal]';
        $errline = $errline ?? 0;
        $ex = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        $this->handleException($ex);

        // Řekneme PHP, že chybu jsme obsloužili
        return true;
    }

    public function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $ex = new \ErrorException($err['message'], 0, $err['type'], $err['file'] ?? '[internal]', $err['line'] ?? 0);

            http_response_code(500);
            if ($this->showDetails) {
                $this->renderHtml($ex);

            } else {
                $this->renderGeneric();
            }
        }
    }

    private function renderGeneric(): void
    {
        echo '
			<div style="background: #111; color: #fff; padding: 18px; border-radius: 10px; margin: 0 auto; font-family: Consolas;">
        		<h3 style="margin: 0 0 8px 0; color: #ff6b6b;">Něco se pokazilo</h3>
        		<p style="margin: 0;">Problém byl zaznamenán. Pokud potíže přetrvávají, kontaktuj administrátora.</p>
        	</div>';
    }

    private function renderHtml(\Throwable $e): void
    {
        $msg  = $this->esc($e->getMessage());
        $file = $this->esc($e->getFile());
        $line = $this->esc((string)$e->getLine());
        $trace = $this->formatTrace($e->getTrace());

        echo <<<HTML
			<div style="
				background: linear-gradient(180deg,#0b0f14 0%, #0f1720 100%);
				color: #fff;
				padding: 20px;
				font-family: Consolas;
				border-radius: 12px;
				margin: 0 auto;
				white-space: pre-wrap;
				line-height: 1.45;
				max-width: 1500px;
				display: flex;
				flex-direction: column;
				justify-content: center;
				top: 50%;
				position: fixed;
				transform: translateY(-50%);
			">
				<h2 style="color: #ff6b6b; margin:0 0 12px 0;">⚠️ Fatální chyba</h2>
				<div style="margin-bottom:6px;"><strong>Zpráva:</strong> {$msg}</div>
				<div style="margin-bottom: 6px;"><strong>Soubor:</strong> {$file}</div>
				<div style="margin-bottom: 12px;"><strong>Řádek:</strong> {$line}</div>
				<hr style="border:none;height:1px;background:#13202a;margin:12px 0;">
				<div><strong>Backtrace:</strong></div>
				<pre style="background: #02101a; padding: 12px; border-radius: 8px; overflow: auto; max-height: 420px;">{$trace}</pre>
			</div>
		HTML;
    }

    private function formatTrace(array $trace): string
    {
        $out = '';
        foreach ($trace as $i => $t) {
            $file = $t['file'] ?? '[internal]';
            $line = $t['line'] ?? '?';
            $class = $t['class'] ?? '';
            $type = $t['type'] ?? '';
            $function = $t['function'] ?? '';
            $args = $this->shortenArgs($t['args'] ?? []);
            $out .= "#{$i} {$this->esc($file)}({$this->esc((string)$line)}): {$this->esc($class)}{$this->esc($type)}{$this->esc($function)}({$this->esc($args)})\n";
        }
        return $out;
    }

    private function shortenArgs(array $args): string
    {
        $parts = [];
        foreach ($args as $a) {
            if (is_object($a)) {
                $parts[] = 'Object(' . get_class($a) . ')';
            } elseif (is_array($a)) {
                $parts[] = 'Array[' . count($a) . ']';
            } elseif (is_string($a)) {
                $s = strlen($a) > 40 ? substr($a, 0, 37) . '...' : $a;
                $parts[] = '"' . $s . '"';
            } elseif (is_null($a)) {
                $parts[] = 'null';
            } elseif (is_bool($a)) {
                $parts[] = $a ? 'true' : 'false';
            } else {
                $parts[] = (string)$a;
            }
            if (count($parts) > 8) {
                $parts[] = '...';
                break;
            }
        }
        return implode(', ', $parts);
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
