<?php

declare(strict_types=1);

namespace Zoom\PlatformBridge\Security;

/**
 * Security boundary pro podepisování a ověřování parametrů.
 *
 * Všechny parametry přenášené mezi nedůvěryhodnými kontexty
 * by měly procházet přes tuto třídu.
 *
 * Funkce:
 * - podepsání payloadu (`sign`)
 * - ověření a rozbalení (`verify`)
 * - bezpečná validace bez výjimek (`isValid`)
 *
 * Formát:
 * base64url(json({ p: payload_json, s: hmac_signature }))
 *
 * Bezpečnost:
 * - secret key min. 32 znaků (256 bit)
 * - TTL chrání proti replay útokům (doporučeno)
 *
 * @see SecurityException
 */
final class SignedParams
{
    private const ALGORITHM = 'sha256';
    private const VERSION = 1;

    /**
     * @param string $secretKey Secret key pro HMAC podpis (min. 32 znaků)
     * @param int|null $ttl Platnost podpisu v sekundách (null = bez expirace)
     */
    public function __construct(private readonly string $secretKey, private readonly ?int $ttl = null) {
        if (strlen($secretKey) < 32) {
            throw new \InvalidArgumentException(
                'Secret key must be at least 32 characters long for security.'
            );
        }
    }

    /**
     * Podepíše a zakóduje parametry do jednoho stringu (anti-tampering).
     *
     * @param array<string,array<string,mixed>> $params
     * @return string Podepsaný payload
     *
     * @example $signed = $signer->sign(['get' => [], 'body' => []]);
	 *
	 * @throws \RuntimeException Pokud selže JSON encoding
     */
    public function sign(array $params): string
    {
        $timestamp = time();

        $payload = [
            'v' => self::VERSION,
            'd' => $params,
            't' => $timestamp,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payloadJson === false) {
            throw new \RuntimeException('Failed to encode params to JSON: ' . json_last_error_msg());
        }

        $signature = $this->createSignature($payloadJson);

        $signedPayload = [
            'p' => $payloadJson,
            's' => $signature,
        ];

        return $this->encode($signedPayload);
    }

    /**
     * Ověří podpis, expiraci a vrátí původní parametry.
     *
     * @param string $signedString Podepsaný string ze sign()
     * @return array<string,array<string,mixed>> Původní parametry
     *
     * @throws SecurityException Pokud je payload neplatný, podpis nesouhlasí nebo vypršela TTL.
     *
     * @example
     *   $params = $signer->verify($signed);
     */
    public function verify(string $signedString): array
    {
        $signedPayload = $this->decode($signedString);

        if (!isset($signedPayload['p'], $signedPayload['s'])) {
            throw new SecurityException('Invalid signed payload structure.');
        }

        $payloadJson = $signedPayload['p'];
        $providedSignature = $signedPayload['s'];

        // Ověření podpisu
        $expectedSignature = $this->createSignature($payloadJson);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new SecurityException('Invalid signature - data may have been tampered with.');
        }

        // Dekódování payload
        $payload = json_decode($payloadJson, true);

        if ($payload === null) {
            throw new SecurityException('Failed to decode payload JSON.');
        }

        // Kontrola verze
        if (($payload['v'] ?? 0) !== self::VERSION) {
            throw new SecurityException('Unsupported payload version.');
        }

        // Kontrola expirace
        if ($this->ttl !== null) {
            $timestamp = $payload['t'] ?? 0;
            $age = time() - $timestamp;

            if ($age > $this->ttl) {
                throw new SecurityException(
                    sprintf('Signed params expired (%d seconds old, TTL is %d).', $age, $this->ttl)
                );
            }

            if ($age < 0) {
                throw new SecurityException('Invalid timestamp - appears to be from the future.');
            }
        }

        return $payload['d'] ?? [];
    }

    /**
     * Ověří, zda je podpis platný (bez vyhození výjimky).
     * Interně používá verify(), ale nevyhazuje výjimky.
     *
     * @param string $signedString Podepsaný string
     * @return bool True pokud je platný, jinak false
     */
    public function isValid(string $signedString): bool
    {
        try {
            $this->verify($signedString);
            return true;
        } catch (SecurityException) {
            return false;
        }
    }

    /**
     * Vytvoří HMAC podpis.
     *
     * @param string $data Data pro podepsání (obvykle JSON string)
     * @return string HMAC podpis
     */
    private function createSignature(string $data): string
    {
        return hash_hmac(self::ALGORITHM, $data, $this->secretKey);
    }

    /**
     * Zakóduje payload do URL-safe base64.
    *
     * @param array $data Data pro zakódování
     * @return string Zakódovaný payload
     */
    private function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Dekóduje URL-safe base64 payload.
     * Očekává formát base64url(json({ p: payload_json, s: hmac_signature }))
     *
     * @param string $encoded Zakódovaný payload
     * @return array Dekódovaný payload jako asociativní pole
     */
    private function decode(string $encoded): array
    {
        $json = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($json === false) {
            throw new SecurityException('Failed to decode base64 payload.');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new SecurityException('Invalid payload format.');
        }

        return $data;
    }

    /**
     * Vygeneruje bezpečný náhodný secret key (hex-encoded).
     *
     * @param int $length Délka klíče v bytech (výchozí 32 = 256 bit)
     * @return string Hexadecimální klíč (2 znaky na byte)
     *
     * @example
     *   $key = SignedParams::generateSecretKey(32); // 64 znaků
     */
    public static function generateSecretKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
