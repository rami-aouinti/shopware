import template from './lieferzeiten-open.html.twig';

Shopware.Component.register('lieferzeiten-open', {
    template,

    props: {
        orders: {
            type: Array,
            required: true,
            default: () => [],
        },
        selectedDomain: {
            type: String,
            required: false,
            default: null,
        },
        onReloadOrder: {
            type: Function,
            required: false,
            default: null,
        },
        statisticsMetrics: {
            type: Object,
            required: false,
            default: () => ({
                openOrders: 0,
                overdueShipping: 0,
                overdueDelivery: 0,
            }),
        },
    },
});
