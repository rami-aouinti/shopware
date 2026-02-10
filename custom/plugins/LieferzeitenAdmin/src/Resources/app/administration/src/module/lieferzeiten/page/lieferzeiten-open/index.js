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
    },

    computed: {
        openOrders() {
            return this.orders.filter((order) => this.isOrderOpen(order));
        },
        openParcelsCount() {
            return this.openOrders.reduce((total, order) => {
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
