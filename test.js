/**
 * AI Form Generator - Modulární architektura
 * ===========================================
 */

(function() {
	'use strict';

	// ============================================
	// UTILITY FUNCTIONS
	// ============================================
	const Utils = {
		/**
		 * Debounce funkce pro optimalizaci výkonu
		 */
		debounce(fn, delay = 300) {
			let timeoutId;
			return function(...args) {
				clearTimeout(timeoutId);
				timeoutId = setTimeout(() => fn.apply(this, args), delay);
			};
		},

		/**
		 * Bezpečné parsování JSON
		 */
		parseJSON(str, fallback = null) {
			try {
				return JSON.parse(str);
			} catch {
				return fallback;
			}
		},

		/**
		 * Deep clone objektu
		 */
		deepClone(obj) {
			return JSON.parse(JSON.stringify(obj));
		},

		/**
		 * Získání hodnoty z nested objektu pomocí path
		 */
		getNestedValue(obj, path) {
			return path.split('.').reduce((current, key) => current?.[key], obj);
		}
	};

	// ============================================
	// EVENT BUS - Centrální komunikace mezi moduly
	// ============================================
	const EventBus = {
		_events: {},

		on(event, callback) {
			if (!this._events[event]) {
				this._events[event] = [];
			}
			this._events[event].push(callback);
			return () => this.off(event, callback);
		},

		off(event, callback) {
			if (!this._events[event]) return;
			this._events[event] = this._events[event].filter(cb => cb !== callback);
		},

		emit(event, data) {
			if (!this._events[event]) return;
			this._events[event].forEach(callback => callback(data));
		},

		once(event, callback) {
			const unsubscribe = this.on(event, (data) => {
				callback(data);
				unsubscribe();
			});
		}
	};

	// ============================================
	// FORM STATE MANAGER - Správa stavu formuláře
	// ============================================
	const FormState = {
		_state: {},
		_initialState: {},
		_rules: {},

		init(form) {
			this._form = form;
			this._collectInitialState();
			this._parseRules();
			return this;
		},

		_collectInitialState() {
			const formData = new FormData(this._form);
			this._state = {};

			// Zpracování všech polí
			this._form.querySelectorAll('[name]').forEach(field => {
				const name = field.name;

				if (field.type === 'checkbox') {
					this._state[name] = field.checked;
				} else if (field.type === 'radio') {
					if (field.checked) {
						this._state[name] = field.value;
					}
				} else {
					this._state[name] = field.value;
				}
			});

			// Nastavení výchozích hodnot pro radio, které nemají nic zaškrtnuté
			this._form.querySelectorAll('input[type="radio"]').forEach(radio => {
				if (!this._state.hasOwnProperty(radio.name)) {
					const checkedRadio = this._form.querySelector(`input[name="${radio.name}"]:checked`);
					this._state[radio.name] = checkedRadio ? checkedRadio.value : '';
				}
			});

			this._initialState = Utils.deepClone(this._state);
		},

		_parseRules() {
			// Pravidla jsou uložena v data atributech
			this._form.querySelectorAll('[data-rules]').forEach(field => {
				const rules = Utils.parseJSON(field.dataset.rules, {});
				const name = field.name || field.dataset.fieldName;
				if (name) {
					this._rules[name] = rules;
				}
			});
		},

		get(key) {
			return key ? this._state[key] : Utils.deepClone(this._state);
		},

		set(key, value) {
			const oldValue = this._state[key];
			this._state[key] = value;

			if (oldValue !== value) {
				EventBus.emit('state:changed', { key, value, oldValue });
				EventBus.emit(`state:changed:${key}`, { value, oldValue });
			}
		},

		getAll() {
			return Utils.deepClone(this._state);
		},

		getRules(fieldName) {
			return this._rules[fieldName] || {};
		},

		getAllRules() {
			return Utils.deepClone(this._rules);
		},

		reset() {
			this._state = Utils.deepClone(this._initialState);
			EventBus.emit('state:reset', this._state);
		},

		isDirty() {
			return JSON.stringify(this._state) !== JSON.stringify(this._initialState);
		}
	};

	// ============================================
	// VISIBILITY CONTROLLER - Podmíněná viditelnost
	// ============================================
	const VisibilityController = {
		_conditions: new Map(),

		init(form) {
			this._form = form;
			this._parseConditions();
			this._bindEvents();
			this._evaluateAll();
			return this;
		},

		_parseConditions() {
			// Hledáme prvky s data-visible-if atributem
			this._form.querySelectorAll('[data-visible-if]').forEach(element => {
				const condition = Utils.parseJSON(element.dataset.visibleIf, null);
				if (condition) {
					this._conditions.set(element, condition);
				}
			});
		},

		_bindEvents() {
			EventBus.on('state:changed', () => {
				this._evaluateAll();
			});
		},

		_evaluateAll() {
			this._conditions.forEach((condition, element) => {
				const isVisible = this._evaluateCondition(condition);
				this._toggleVisibility(element, isVisible);
			});
		},

		_evaluateCondition(condition) {
			// Podmínka může být objekt { fieldName: expectedValue }
			// nebo komplexnější { and: [...], or: [...] }

			if (condition.and) {
				return condition.and.every(c => this._evaluateCondition(c));
			}

			if (condition.or) {
				return condition.or.some(c => this._evaluateCondition(c));
			}

			if (condition.not) {
				return !this._evaluateCondition(condition.not);
			}

			// Jednoduchá podmínka { fieldName: value }
			return Object.entries(condition).every(([fieldName, expectedValue]) => {
				const currentValue = FormState.get(fieldName);

				// Podpora pro pole hodnot
				if (Array.isArray(expectedValue)) {
					return expectedValue.includes(currentValue);
				}

				// Podpora pro negaci
				if (typeof expectedValue === 'object' && expectedValue !== null) {
					if ('not' in expectedValue) {
						return currentValue !== expectedValue.not;
					}
				}

				return currentValue === expectedValue;
			});
		},

		_toggleVisibility(element, isVisible) {
			const wrapper = element.closest('.ai-form__field') || element;

			if (isVisible) {
				wrapper.classList.remove('ai-form__field--hidden');
				wrapper.style.display = '';
				// Povolit pole při zobrazení
				const inputs = wrapper.querySelectorAll('input, select, textarea');
				inputs.forEach(input => input.disabled = false);
			} else {
				wrapper.classList.add('ai-form__field--hidden');
				wrapper.style.display = 'none';
				// Zakázat pole při skrytí
				const inputs = wrapper.querySelectorAll('input, select, textarea');
				inputs.forEach(input => input.disabled = true);
			}

			EventBus.emit('visibility:changed', { element, isVisible });
		},

		isVisible(element) {
			const condition = this._conditions.get(element);
			return condition ? this._evaluateCondition(condition) : true;
		}
	};

	// ============================================
	// FORM VALIDATOR - Validace formuláře
	// ============================================
	const FormValidator = {
		_errors: new Map(),
		_validators: {},

		init(form) {
			this._form = form;
			this._registerBuiltInValidators();
			return this;
		},

		_registerBuiltInValidators() {
			this._validators = {
				required: (value, params, field) => {
					if (typeof value === 'boolean') return value === true;
					if (typeof value === 'string') return value.trim().length > 0;
					return value !== null && value !== undefined;
				},

				minlength: (value, minLength) => {
					return typeof value === 'string' && value.length >= minLength;
				},

				maxlength: (value, maxLength) => {
					return typeof value === 'string' && value.length <= maxLength;
				},

				min: (value, minValue) => {
					const num = parseFloat(value);
					return !isNaN(num) && num >= minValue;
				},

				max: (value, maxValue) => {
					const num = parseFloat(value);
					return !isNaN(num) && num <= maxValue;
				},

				pattern: (value, pattern) => {
					const regex = new RegExp(pattern);
					return regex.test(value);
				},

				email: (value) => {
					const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
					return value === '' || emailRegex.test(value);
				},

				url: (value) => {
					try {
						new URL(value);
						return true;
					} catch {
						return value === '';
					}
				}
			};
		},

		registerValidator(name, fn) {
			this._validators[name] = fn;
		},

		validate(fieldName = null) {
			this._errors.clear();

			const fields = fieldName
				? [this._form.querySelector(`[name="${fieldName}"]`)]
				: this._form.querySelectorAll('[name]');

			fields.forEach(field => {
				if (!field || field.disabled) return;

				const name = field.name;
				const value = FormState.get(name);
				const rules = FormState.getRules(name);

				// Přeskočit validaci skrytých polí
				const wrapper = field.closest('.ai-form__field');
				if (wrapper && wrapper.classList.contains('ai-form__field--hidden')) {
					return;
				}

				const fieldErrors = this._validateField(name, value, rules, field);
				if (fieldErrors.length > 0) {
					this._errors.set(name, fieldErrors);
				}
			});

			const isValid = this._errors.size === 0;
			EventBus.emit('validation:complete', { isValid, errors: this.getErrors() });

			return isValid;
		},

		_validateField(name, value, rules, field) {
			const errors = [];

			Object.entries(rules).forEach(([ruleName, ruleParams]) => {
				// Přeskočit pravidla, která nejsou validátory
				if (['default', 'visible_if'].includes(ruleName)) return;

				const validator = this._validators[ruleName];
				if (validator && !validator(value, ruleParams, field)) {
					errors.push({
						rule: ruleName,
						message: this._getErrorMessage(ruleName, ruleParams, name)
					});
				}
			});

			return errors;
		},

		_getErrorMessage(rule, params, fieldName) {
			const messages = {
				required: `Pole je povinné`,
				minlength: `Minimální délka je ${params} znaků`,
				maxlength: `Maximální délka je ${params} znaků`,
				min: `Minimální hodnota je ${params}`,
				max: `Maximální hodnota je ${params}`,
				pattern: `Neplatný formát`,
				email: `Neplatná e-mailová adresa`,
				url: `Neplatná URL adresa`
			};

			return messages[rule] || `Validace ${rule} selhala`;
		},

		getErrors(fieldName = null) {
			if (fieldName) {
				return this._errors.get(fieldName) || [];
			}
			return Object.fromEntries(this._errors);
		},

		isValid() {
			return this._errors.size === 0;
		},

		showErrors() {
			// Odstranit předchozí chybové zprávy
			this._form.querySelectorAll('.ai-form__error').forEach(el => el.remove());
			this._form.querySelectorAll('.ai-form__field--error').forEach(el => {
				el.classList.remove('ai-form__field--error');
			});

			this._errors.forEach((errors, fieldName) => {
				const field = this._form.querySelector(`[name="${fieldName}"]`);
				if (!field) return;

				const wrapper = field.closest('.ai-form__field') || field.parentElement;
				wrapper.classList.add('ai-form__field--error');

				errors.forEach(error => {
					const errorEl = document.createElement('span');
					errorEl.className = 'ai-form__error';
					errorEl.textContent = error.message;
					wrapper.appendChild(errorEl);
				});
			});
		},

		clearErrors() {
			this._errors.clear();
			this._form.querySelectorAll('.ai-form__error').forEach(el => el.remove());
			this._form.querySelectorAll('.ai-form__field--error').forEach(el => {
				el.classList.remove('ai-form__field--error');
			});
		}
	};

	// ============================================
	// FORM COLLECTOR - Sběr dat z formuláře
	// ============================================
	const FormCollector = {
		init(form) {
			this._form = form;
			return this;
		},

		/**
		 * Sbírá pouze viditelná a povolená pole
		 */
		collect() {
			const data = {};

			this._form.querySelectorAll('[name]').forEach(field => {
				// Přeskočit zakázaná pole
				if (field.disabled) return;

				const name = field.name;

				if (field.type === 'checkbox') {
					data[name] = field.checked;
				} else if (field.type === 'radio') {
					if (field.checked) {
						data[name] = field.value;
					}
				} else {
					data[name] = field.value;
				}
			});

			return data;
		},

		/**
		 * Sbírá všechna pole včetně zakázaných
		 */
		collectAll() {
			return FormState.getAll();
		},

		/**
		 * Vrací data jako FormData objekt
		 */
		toFormData() {
			return new FormData(this._form);
		},

		/**
		 * Vrací data jako JSON string
		 */
		toJSON() {
			return JSON.stringify(this.collect());
		},

		/**
		 * Vrací data jako URL encoded string
		 */
		toURLEncoded() {
			const data = this.collect();
			return new URLSearchParams(data).toString();
		}
	};

	// ============================================
	// API CLIENT - Komunikace se serverem
	// ============================================
	const ApiClient = {
		_baseUrl: '',
		_defaultHeaders: {
			'Content-Type': 'application/json'
		},

		init(config = {}) {
			this._baseUrl = config.baseUrl || '';
			if (config.headers) {
				this._defaultHeaders = { ...this._defaultHeaders, ...config.headers };
			}
			return this;
		},

		async request(endpoint, options = {}) {
			const url = this._baseUrl + endpoint;
			const config = {
				method: options.method || 'POST',
				headers: { ...this._defaultHeaders, ...options.headers },
				...options
			};

			if (options.body && typeof options.body === 'object') {
				config.body = JSON.stringify(options.body);
			}

			EventBus.emit('api:request:start', { url, config });

			try {
				const response = await fetch(url, config);
				const data = await response.json();

				if (!response.ok) {
					throw new ApiError(response.status, data.message || 'Request failed', data);
				}

				EventBus.emit('api:request:success', { url, data });
				return data;

			} catch (error) {
				EventBus.emit('api:request:error', { url, error });
				throw error;
			} finally {
				EventBus.emit('api:request:end', { url });
			}
		},

		async generate(formData) {
			return this.request('/api/generate', {
				method: 'POST',
				body: formData
			});
		}
	};

	// Custom API Error
	class ApiError extends Error {
		constructor(status, message, data) {
			super(message);
			this.name = 'ApiError';
			this.status = status;
			this.data = data;
		}
	}

	// ============================================
	// UI CONTROLLER - Správa UI komponent
	// ============================================
	const UIController = {
		_elements: {},

		init(selectors) {
			this._cacheElements(selectors);
			return this;
		},

		_cacheElements(selectors) {
			Object.entries(selectors).forEach(([key, selector]) => {
				this._elements[key] = document.querySelector(selector);
			});
		},

		get(name) {
			return this._elements[name];
		},

		showLoader(target = 'generateButton') {
			const element = this._elements[target];
			if (!element) return;

			element.classList.add('ai-button--loading');
			element.disabled = true;
			element.dataset.originalText = element.textContent;
			element.innerHTML = '<span class="ai-spinner"></span> Generuji...';
		},

		hideLoader(target = 'generateButton') {
			const element = this._elements[target];
			if (!element) return;

			element.classList.remove('ai-button--loading');
			element.disabled = false;
			element.textContent = element.dataset.originalText || 'GENEROVAT TEXT';
		},

		showNotification(message, type = 'info', duration = 5000) {
			const notification = document.createElement('div');
			notification.className = `ai-notification ai-notification--${type}`;
			notification.innerHTML = `
				<span class="ai-notification__message">${message}</span>
				<button class="ai-notification__close">&times;</button>
			`;

			document.body.appendChild(notification);

			// Animace vstupu
			requestAnimationFrame(() => {
				notification.classList.add('ai-notification--visible');
			});

			// Zavření
			const close = () => {
				notification.classList.remove('ai-notification--visible');
				setTimeout(() => notification.remove(), 300);
			};

			notification.querySelector('.ai-notification__close').addEventListener('click', close);

			if (duration > 0) {
				setTimeout(close, duration);
			}

			return { close };
		},

		showResult(result) {
			let resultContainer = document.querySelector('.ai-result');

			if (!resultContainer) {
				resultContainer = document.createElement('div');
				resultContainer.className = 'ai-result';
				this._elements.form?.parentElement.appendChild(resultContainer);
			}

			resultContainer.innerHTML = `
				<h3 class="ai-result__title">Vygenerovaný text</h3>
				<div class="ai-result__content">${this._escapeHtml(result)}</div>
				<div class="ai-result__actions">
					<button class="ai-button ai-button--secondary" data-action="copy">
						Kopírovat
					</button>
					<button class="ai-button ai-button--secondary" data-action="regenerate">
						Regenerovat
					</button>
				</div>
			`;

			resultContainer.classList.add('ai-result--visible');

			// Event handlery pro tlačítka
			resultContainer.querySelector('[data-action="copy"]')?.addEventListener('click', () => {
				navigator.clipboard.writeText(result).then(() => {
					this.showNotification('Text zkopírován do schránky', 'success', 2000);
				});
			});

			resultContainer.querySelector('[data-action="regenerate"]')?.addEventListener('click', () => {
				EventBus.emit('form:regenerate');
			});
		},

		_escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	};

	// ============================================
	// FORM CONTROLLER - Hlavní kontrolér formuláře
	// ============================================
	const FormController = {
		init(formSelector) {
			this._form = document.querySelector(formSelector);
			if (!this._form) {
				console.error(`Form not found: ${formSelector}`);
				return this;
			}

			// Inicializace modulů
			FormState.init(this._form);
			VisibilityController.init(this._form);
			FormValidator.init(this._form);
			FormCollector.init(this._form);
			ApiClient.init({ baseUrl: window.location.origin });
			UIController.init({
				form: formSelector,
				generateButton: '#generateButton',
				resultContainer: '.ai-result'
			});

			this._bindEvents();

			EventBus.emit('form:initialized', { form: this._form });

			return this;
		},

		_bindEvents() {
			// Sledování změn ve formuláři
			this._form.addEventListener('input', Utils.debounce((e) => {
				this._handleFieldChange(e.target);
			}, 150));

			this._form.addEventListener('change', (e) => {
				this._handleFieldChange(e.target);
			});

			// Odeslání formuláře
			this._form.addEventListener('submit', (e) => {
				e.preventDefault();
				this.submit();
			});

			// Generate button
			const generateBtn = document.getElementById('generateButton');
			if (generateBtn) {
				generateBtn.addEventListener('click', (e) => {
					e.preventDefault();
					this.submit();
				});
			}

			// Regenerace
			EventBus.on('form:regenerate', () => {
				this.submit();
			});

			// API události
			EventBus.on('api:request:start', () => {
				UIController.showLoader();
			});

			EventBus.on('api:request:end', () => {
				UIController.hideLoader();
			});
		},

		_handleFieldChange(field) {
			if (!field.name) return;

			let value;
			if (field.type === 'checkbox') {
				value = field.checked;
			} else if (field.type === 'radio') {
				if (field.checked) {
					value = field.value;
				} else {
					return;
				}
			} else {
				value = field.value;
			}

			FormState.set(field.name, value);

			// Validace jednotlivého pole při změně
			FormValidator.validate(field.name);
		},

		async submit() {
			FormValidator.clearErrors();

			if (!FormValidator.validate()) {
				FormValidator.showErrors();
				UIController.showNotification('Prosím opravte chyby ve formuláři', 'error');
				return;
			}

			const formData = FormCollector.collect();

			EventBus.emit('form:submit:start', { data: formData });

			try {
				const result = await ApiClient.generate(formData);

				EventBus.emit('form:submit:success', { result });
				UIController.showResult(result.text || result.data || result);
				UIController.showNotification('Text byl úspěšně vygenerován', 'success');

			} catch (error) {
				EventBus.emit('form:submit:error', { error });

				if (error instanceof ApiError) {
					UIController.showNotification(`Chyba: ${error.message}`, 'error');
				} else {
					UIController.showNotification('Nepodařilo se vygenerovat text. Zkuste to prosím znovu.', 'error');
				}

				console.error('Form submission error:', error);
			}
		},

		reset() {
			this._form.reset();
			FormState.reset();
			FormValidator.clearErrors();
			EventBus.emit('form:reset');
		},

		getState() {
			return FormState.getAll();
		},

		getData() {
			return FormCollector.collect();
		}
	};

	// ============================================
	// INITIALIZATION
	// ============================================
	window.addEventListener('DOMContentLoaded', function() {
		// Inicializace hlavního kontroléru
		const app = FormController.init('.ai-form');

		// Expose pro debugging a externí přístup
		window.AIForm = {
			controller: FormController,
			state: FormState,
			events: EventBus,
			validator: FormValidator,
			collector: FormCollector,
			api: ApiClient,
			ui: UIController,
			visibility: VisibilityController,
			utils: Utils
		};

		console.log('AI Form Generator initialized');
	});

})();