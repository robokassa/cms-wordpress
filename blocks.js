const paymentMethods = [
    {
        name: 'robokassa',
        fallback: window.wp.i18n.__('Оплата через Robokassa', 'robokassa'),
    },
    {
        name: 'robokassa_credit',
        fallback: window.wp.i18n.__('Рассрочка или кредит', 'robokassa'),
    },
    {
        name: 'robokassa_podeli',
        fallback: window.wp.i18n.__('Robokassa Х Подели', 'robokassa'),
    },
    {
        name: 'robokassa_mokka',
        fallback: window.wp.i18n.__('Robokassa Х Mokka', 'robokassa'),
    },
    {
        name: 'robokassa_split',
        fallback: window.wp.i18n.__('Robokassa X Яндекс Сплит', 'robokassa'),
    },
];

paymentMethods.forEach((config) => {
    const settings = window.wc.wcSettings.getSetting(`${config.name}_data`, null);
    if (!settings) {
        return;
    }

    const label = window.wp.htmlEntities.decodeEntities(
        settings.title || config.fallback
    );

    const Content = () => window.wp.htmlEntities.decodeEntities(settings.description || '');

    window.wc.wcBlocksRegistry.registerPaymentMethod({
        name: config.name,
        label,
        content: Object(window.wp.element.createElement)(Content, null),
        edit: Object(window.wp.element.createElement)(Content, null),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || [],
        },
    });
});

const ROBOKASSA_QUERY_MAPPING = {
    podeli: 'robokassa_podeli',
    otp: 'robokassa_credit',
    mokka: 'robokassa_mokka',
    yandexpaysplit: 'robokassa_split',
};

function robokassaNormalizeGateway(label) {
    if (!label) {
        return '';
    }

    const normalized = String(label).toLowerCase();

    if (Object.prototype.hasOwnProperty.call(ROBOKASSA_QUERY_MAPPING, normalized)) {
        return ROBOKASSA_QUERY_MAPPING[normalized];
    }

    return normalized.indexOf('robokassa') === 0 ? normalized : '';
}

function robokassaGetGatewayFromSearch(searchParams) {
    const fromParam = robokassaNormalizeGateway(searchParams.get('payment_method'));

    if (fromParam) {
        return fromParam;
    }

    return searchParams.get('source') === 'robokassa_widget' ? 'robokassa' : '';
}

function robokassaGetCheckoutDispatcher() {
    if (!window.wp || !window.wp.data || typeof window.wp.data.dispatch !== 'function') {
        return null;
    }

    return window.wp.data.dispatch('wc/store/checkout') || null;
}

function robokassaInvokeCheckoutSelection(dispatcher, gatewayId) {
    const actions = [
        'setSelectedPaymentMethod',
        '__experimentalSetSelectedPaymentMethod',
        'selectPaymentMethod',
        'setPaymentMethod',
    ];

    for (let index = 0; index < actions.length; index += 1) {
        const action = actions[index];

        if (typeof dispatcher[action] === 'function') {
            dispatcher[action](gatewayId);
            return true;
        }
    }

    return false;
}

function robokassaIsSelectionConfirmed(gatewayId) {
    if (!window.wp || !window.wp.data || typeof window.wp.data.select !== 'function') {
        return true;
    }

    const selector = window.wp.data.select('wc/store/checkout');

    if (!selector || typeof selector.getSelectedPaymentMethod !== 'function') {
        return true;
    }

    const selected = selector.getSelectedPaymentMethod();

    if (!selected) {
        return false;
    }

    if (typeof selected === 'string') {
        return selected === gatewayId;
    }

    return Boolean(selected.name === gatewayId);
}

function robokassaSelectGateway(gatewayId) {
    const dispatcher = robokassaGetCheckoutDispatcher();

    if (!dispatcher) {
        return false;
    }

    if (!robokassaInvokeCheckoutSelection(dispatcher, gatewayId)) {
        return false;
    }

    return robokassaIsSelectionConfirmed(gatewayId);
}

function robokassaEnsureGatewaySelection(gatewayId, attempt) {
    if (!gatewayId) {
        return;
    }

    if (robokassaSelectGateway(gatewayId)) {
        return;
    }

    if (attempt >= 10) {
        return;
    }

    window.setTimeout(function retrySelection() {
        robokassaEnsureGatewaySelection(gatewayId, attempt + 1);
    }, 200);
}

function robokassaInitGatewaySelection() {
    const params = new window.URLSearchParams(window.location.search);
    const gatewayId = robokassaGetGatewayFromSearch(params);

    robokassaEnsureGatewaySelection(gatewayId, 0);
}

// === Robokassa Debug & Fallback Auto-Selection ===
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const methodParam = urlParams.get('payment_method');

    if (!methodParam) return;

    const mapping = {
        podeli: 'robokassa_podeli',
        otp: 'robokassa_credit',
        mokka: 'robokassa_mokka',
        yandexpaysplit: 'robokassa_split',
    };

    const methodKey = mapping[methodParam.toLowerCase()];

    if (!methodKey) {
        return;
    }

    const interval = setInterval(() => {
        const paymentInputs = document.querySelectorAll('input[name="radio-control-wc-payment-method-options"]');

        for (const input of paymentInputs) {
            if (input.value === methodKey) {
                input.click();
                clearInterval(interval);
                return;
            }
        }
    }, 300);

    setTimeout(() => {
        clearInterval(interval);
    }, 5000);
});

if (window.wp && typeof window.wp.domReady === 'function') {
    window.wp.domReady(robokassaInitGatewaySelection);
} else {
    document.addEventListener('DOMContentLoaded', robokassaInitGatewaySelection);
}