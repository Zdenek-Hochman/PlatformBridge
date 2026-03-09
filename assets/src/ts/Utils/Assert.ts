/**
 * Ověří, že hodnota je pravdivá (truthy).
 * Pokud není, vyhodí chybu s danou zprávou.
 * Používá TypeScript assertion pro lepší typovou kontrolu.
 *
 * @param value - Hodnota, kterou ověřujeme.
 * @param message - Chybová zpráva při selhání.
 * @throws {Error} Pokud je hodnota "falsy".
 *
 * @example
 * assert(user, 'Uživatel musí existovat');
 */
export function assert<T>(value: T, message = 'Assertion failed'): asserts value {
	if (!value) {
		throw new Error(message);
	}
}

/**
 * Ověří, že hodnota není null ani undefined.
 * Pokud je, vyhodí chybu s danou zprávou.
 * Typová aserce zaručí, že hodnota je typu T.
 *
 * @param value - Hodnota, kterou ověřujeme.
 * @param message - Chybová zpráva při selhání.
 * @throws {Error} Pokud je hodnota null nebo undefined.
 *
 * @example
 * assertDefined(data, 'Data musí být definována');
 */
export function assertDefined<T>(value: T | null | undefined, message = 'Value is undefined'): asserts value is T {
	if (value == null) {
		throw new Error(message);
	}
}

/**
 * Pomůcka pro exhaustivní kontrolu v switch/case.
 * Pokud se zavolá, znamená to, že byl předán neočekávaný typ.
 *
 * @param x - Neočekávaná hodnota.
 * @throws {Error} Vždy vyhodí chybu.
 *
 * @example
 * switch (type) {
 *   case 'a': ...; break;
 *   case 'b': ...; break;
 *   default: assertNever(type);
 * }
 */
export function assertNever(x: never): never {
	throw new Error(`Unexpected value: ${x}`);
}

/**
 * Ověří, že podmínka je pravdivá.
 * Pokud není, vyhodí chybu s danou zprávou.
 * Vhodné pro obecné invarianty v kódu.
 *
 * @param condition - Podmínka, kterou ověřujeme.
 * @param message - Chybová zpráva při selhání.
 * @throws {Error} Pokud je podmínka "falsy".
 *
 * @example
 * invariant(array.length > 0, 'Pole nesmí být prázdné');
 */
export function invariant(condition: unknown, message = 'Invariant violated'): asserts condition {
	if (!condition) {
		throw new Error(message);
	}
}