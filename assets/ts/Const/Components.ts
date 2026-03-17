// ─── PlatformBridge – CSS Class Constants ──────────────────────
// Centrální definice VŠECH CSS tříd používaných v aplikaci.
// Jediné místo pro změnu prefixu nebo přejmenování tříd.
//
// Prefix "pb-" (PlatformBridge) minimalizuje kolize s jakoukoliv
// cizí aplikací na stránce.

/** Globální CSS prefix pro všechny BEM bloky */
export const CSS_PREFIX = 'pb' as const;

// ─── Module (hlavní wrapper) ───────────────────────────────────

export const MODULE = {
	ROOT:            'pb-module',
	TITLE:           'pb-module__title',
	FORM:            'pb-module__form',
	WRAPPER:         'pb-module__wrapper',
	SECTION:         'pb-module__section',
	SECTION_GRID:    'pb-module__section--grid',
	BLOCK:           'pb-module__block',
	BLOCK_HIDDEN:    'pb-module__block--hidden',
	RESULT:          'pb-module__result',
	RESULT_TITLE:    'pb-module__result-title',
	FIELD:           'pb-module__field',
	FIELD_ERROR:     'pb-module__field--error',
	INPUT:           'pb-module__input',
	TEXTAREA:        'pb-module__textarea',
	SELECT:          'pb-module__select',
	LABEL:           'pb-module__label',
	LABEL_VALUE:     'pb-module__label-value',
	SMALL:           'pb-module__small',
	TICK_BOX:        'pb-module__tick-box',
	BUTTON:          'pb-module__button',
	BUTTON_PRIMARY:  'pb-module__button--primary',
	ERROR_MSG:       'pb-module__error',
	OVERLAY:         'pb-module__overlay',
	LOADING:         'pb-module--loading',
	SUBMIT_LOADING:  'pb-module__submit--loading',
} as const;

// ─── Result (výsledky) ─────────────────────────────────────────

export const RESULT = {
	ROOT:     'pb-result',
	ITEM:     'pb-result__item',
	WRAPPER:  'pb-result__wrapper',
	LABEL:    'pb-result__label',
	CONTENT:  'pb-result__content',
	ACTIONS:  'pb-result__actions',
	ACTION:   'pb-result__action',
	ICON:     'pb-result__icon',
	USE:      'pb-result__use',
} as const;

// ─── Custom Select ─────────────────────────────────────────────

export const SELECT = {
	ROOT:            'pb-select',
	NATIVE:          'pb-select__native',
	TRIGGER:         'pb-select__trigger',
	DROPDOWN:        'pb-select__dropdown',
	OPTION:          'pb-select__option',
	OPTION_SELECTED: 'pb-select__option--selected',
	OPTION_FOCUSED:  'pb-select__option--focused',
	OPTION_DISABLED: 'pb-select__option--disabled',
	OPEN:            'pb-select--open',
	DISABLED:        'pb-select--disabled',
} as const;

// ─── Loader ────────────────────────────────────────────────────

export const LOADER = {
	ROOT:    'pb-loader',
	SPINNER: 'pb-loader__spinner',
	TEXT:    'pb-loader__text',
	ACTIVE:  'pb-loader--active',
	INLINE:  'pb-loader--inline',
	SM:      'pb-loader--sm',
} as const;

// ─── Notification ──────────────────────────────────────────────

export const NOTIFICATION = {
	ROOT:      'pb-notification',
	CONTAINER: 'pb-notifications',
	ICON:      'pb-notification__icon',
	CONTENT:   'pb-notification__content',
	TITLE:     'pb-notification__title',
	MESSAGE:   'pb-notification__message',
	CLOSE:     'pb-notification__close',
	ERROR:     'pb-notification--error',
	SUCCESS:   'pb-notification--success',
	WARNING:   'pb-notification--warning',
	INFO:      'pb-notification--info',
	LEAVING:   'pb-notification--leaving',
} as const;

// ─── Progress ──────────────────────────────────────────────────

export const PROGRESS = {
	ROOT:                'pb-progress',
	HEADER:              'pb-progress__header',
	PHASES:              'pb-progress__phases',
	PHASE:               'pb-progress__phase',
	PHASE_PENDING:       'pb-progress__phase--pending',
	PHASE_ACTIVE:        'pb-progress__phase--active',
	PHASE_DONE:          'pb-progress__phase--done',
	PHASE_ERROR:         'pb-progress__phase--error',
	PHASE_ICON:          'pb-progress__phase-icon',
	PHASE_LABEL:         'pb-progress__phase-label',
	BAR_WRAPPER:         'pb-progress__bar-wrapper',
	BAR_INFO:            'pb-progress__bar-info',
	BAR:                 'pb-progress__bar',
	BAR_FILL:            'pb-progress__bar-fill',
	MESSAGE:             'pb-progress__message',
	MESSAGE_ERROR:       'pb-progress__message--error',
	COUNTER:             'pb-progress__counter',
	TIMER:               'pb-progress__timer',
	RESULTS:             'pb-progress__results',
	RESULT_ITEM:         'pb-progress__result-item',
	RESULT_ITEM_ENTERING:'pb-progress__result-item--entering',
	RESULT_ITEM_VISIBLE: 'pb-progress__result-item--visible',
	COMPLETE:            'pb-progress--complete',
	ERROR:               'pb-progress--error',
} as const;

// ─── Error Alert ───────────────────────────────────────────────

export const ERROR_ALERT = {
	ROOT:    'pb-error',
	CONTENT: 'pb-error__content',
	TITLE:   'pb-error__title',
	MESSAGE: 'pb-error__message',
	DETAIL:  'pb-error__detail',
	CLOSE:   'pb-error__close',
} as const;

// ─── Animation Utility Classes ─────────────────────────────────

export const ANIMATE = {
	FADE_IN:  'pb-animate-fade-in',
	SLIDE_UP: 'pb-animate-slide-up',
	PULSE:    'pb-animate-pulse',
} as const;

// ─── Data Attribute Values ─────────────────────────────────────

/** Hodnoty používané v data-component atributech */
export const COMPONENTS = {
	RESULT_CONTAINER: 'pb-result',
	HANDLERS:         'pb-handlers',
} as const;

/** Hodnoty používané v data-flag atributech */
export const FLAGS = {
	RESULTS:        'results',
	RESULT_CONTENT: 'result-content',
} as const;

/** Hodnoty používané v data-action atributech */
export const ACTIONS = {
	SEND_REQUEST: 'send-request',
	REPEAT:       'repeat',
	COPY:         'copy',
	USE:          'use',
	THUMB_UP:     'thumb-up',
	THUMB_DOWN:   'thumb-down',
} as const;

/** ID SVG symbolů (href="#pb-...") */
export const ICONS = {
	REPEAT:     'pb-repeat',
	COPY:       'pb-copy',
	THUMB_UP:   'pb-thumb-up',
	THUMB_DOWN: 'pb-thumb-down',
	USE:        'pb-use',
} as const;
