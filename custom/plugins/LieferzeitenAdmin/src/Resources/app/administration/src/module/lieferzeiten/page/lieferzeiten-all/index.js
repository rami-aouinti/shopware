import template from './lieferzeiten-all.html.twig';

Shopware.Component.register('lieferzeiten-all', {
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
    },

    computed: {
        openOrders() {
            return this.orders.filter((order) => this.isOrderOpen(order));
        },
        closedOrders() {
            return this.orders.filter((order) => !this.isOrderOpen(order));
        },
        openParcelsCount() {
            return this.orders.reduce((total, order) => {
                const openParcels = order.parcels.filter((parcel) => !parcel.closed).length;
                return total + openParcels;
            }, 0);
        },
    },

    methods: {
        isOrderOpen(order) {
            return order.parcels.some((parcel) => !parcel.closed);
        },
    },
});
