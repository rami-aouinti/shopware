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

    data() {
        return {
            columns: [
                {
                    property: 'orderNumber',
                    label: 'lieferzeiten.table.orderNumber',
                    primary: true,
                    sortable: false,
                },
                {
                    property: 'domain',
                    label: 'lieferzeiten.table.domain',
                    sortable: false,
                },
                {
                    property: 'parcels',
                    label: 'lieferzeiten.table.openParcels',
                    sortable: false,
                },
                {
                    property: 'status',
                    label: 'lieferzeiten.table.status',
                    sortable: false,
                },
            ],
        };
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
            return order.parcels.every((parcel) => !parcel.closed);
        },
        parcelSummary(order) {
            const openParcels = order.parcels.filter((parcel) => !parcel.closed).length;
            return `${openParcels}/${order.parcels.length}`;
        },
        orderStatusLabel() {
            return this.$t('lieferzeiten.status.open');
        },
    },
});
