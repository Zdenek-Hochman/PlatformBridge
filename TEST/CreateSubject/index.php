<?php

/**
 * TEST endpoint pro CreateSubject
 *
 * Podporuje:
 * - Plné generování (nested response s multiple subjects + preheaders)
 * - Single-key regenerace (jednoduchá textová odpověď pro jeden klíč)
 */

$input = json_decode(file_get_contents("php://input"), true) ?? [];
$generateKey = $input['generate_key'] ?? null;

// ─── Single-key mód ────────────────────────────────────────────
// Pokud je zadán generate_key, vrať pouze jednoduchou textovou odpověď
// pro daný klíč. Šetří tokeny — generuje se pouze jeden výstup.
if ($generateKey !== null && $generateKey !== '') {
	$singleResponses = [
		'subject' => [
			'Nový předmět vygenerovaný z relace (' . date('H:i:s') . ')',
			'Regenerovaný subject pro vaši kampaň',
			'Předmět emailu — aktualizace',
		],
		'preheader' => [
			'Nový preheader z relace (' . date('H:i:s') . ')',
			'Aktualizovaný preheader pro lepší otevíratelnost',
			'Preheader — regenerace',
		],
	];

	$pool = $singleResponses[$generateKey] ?? ["Vygenerovaný text pro klíč: {$generateKey}"];
	$text = $pool[array_rand($pool)];

	die(json_encode([
		'data' => $text,
		'request_id' => bin2hex(random_bytes(16)),
		'timestamp' => gmdate('c'),
		'usage' => [
			'tokens_total' => 45,
			'credits_total' => 0.045,
			'eur_total' => 0.000068
		]
	], JSON_UNESCAPED_UNICODE));
}

// ─── Plný mód (nested response) ────────────────────────────────
die(json_encode([
	'data' => [
		[
			"subject" => "Sejdeme se na Vánoce?",
			"preheader" => "Přijdte ochutnat naše speciální vánoční menu"
		],
		[
			"subject" => "Milujeme vánoční cukroví?",
			"preheader" => "Objednejte si naše vánoční cukroví ještě dnes"
		]
	],
	'request_id' => bin2hex(random_bytes(16)),
	'timestamp' => gmdate('c'),
	'usage' => [
		'tokens_total' => 1234,
		'credits_total' => 1.234,
		'eur_total' => 0.001852
	]
], JSON_UNESCAPED_UNICODE));
?>