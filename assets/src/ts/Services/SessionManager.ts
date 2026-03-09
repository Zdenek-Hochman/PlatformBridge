/**
 * SessionManager - Správa relace (session) poslední hlavní generace
 *
 * Při plném generování (hlavní tlačítko) se formulářová data + konfigurace
 * uloží jako "relace". Při regeneraci jednoho klíče (repeat) se data
 * berou z této relace, nikoliv z aktuálního formuláře.
 *
 * Tím se zajistí konzistence dat a není nutné znovu číst formulář,
 * který mohl být mezitím změněn.
 */

export interface SessionData {
	/** Kompletní formulářová data včetně __ai_signed */
	formData: Record<string, unknown>;
	/** Čas vytvoření relace */
	createdAt: number;
}

export class SessionManager {
	/** Aktuální relace */
	private currentSession: SessionData | null = null;

	/**
	 * Uloží data z hlavní generace jako novou relaci.
	 * Volá se po úspěšném odeslání hlavního formuláře.
	 *
	 * @param formData Objekt s daty formuláře, která mají být uložena do relace
	 */
	save(formData: Record<string, unknown>): void {
		this.currentSession = {
			formData: { ...formData },
			createdAt: Date.now(),
		};
	}

	/**
	 * Vrátí aktuální relaci nebo null.
	 *
	 * @returns Objekt aktuální relace, nebo null pokud žádná neexistuje
	 */
	get(): SessionData | null {
		return this.currentSession;
	}

	/**
	 * Vrátí formulářová data z aktuální relace.
	 *
	 * @returns Objekt s formulářovými daty, nebo null pokud relace neexistuje
	 */
	getFormData(): Record<string, unknown> | null {
		return this.currentSession?.formData ?? null;
	}

	/**
	 * Sestaví data pro regeneraci jednoho klíče.
	 *
	 * Vezme uložená formulářová data z relace a přidá __generate_key,
	 * čímž backendu signalizuje single-key mód.
	 *
	 * @param generateKey Klíč k regeneraci (např. "subject", "preheader")
	 * @returns Data pro odeslání do API, nebo null pokud relace neexistuje
	 */
	buildSingleKeyPayload(generateKey: string): Record<string, unknown> | null {
		const formData = this.getFormData();

		if (!formData) {
			return null;
		}

		return {
			...formData,
			__generate_key: generateKey,
		};
	}

	/**
	 * Zkontroluje, zda existuje aktivní relace.
	 */
	hasSession(): boolean {
		return this.currentSession !== null;
	}

	/**
	 * Vrátí stáří relace v milisekundách.
	 */
	getAge(): number | null {
		if (!this.currentSession) {
			return null;
		}
		return Date.now() - this.currentSession.createdAt;
	}

	/**
	 * Vymaže relaci.
	 */
	clear(): void {
		this.currentSession = null;
	}
}
