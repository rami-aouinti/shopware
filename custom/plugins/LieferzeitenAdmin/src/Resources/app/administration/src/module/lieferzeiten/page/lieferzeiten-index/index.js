import template from './lieferzeiten-index.html.twig';

Shopware.Component.register('lieferzeiten-index', {
    template,

    inject: [
        'lieferzeitenOrdersService',
    ],

    data() {
        return {
            selectedDomain: null,
            orders: [],
            isLoading: false,
            loadError: null,
        };
    },

    created() {
        this.loadOrders();
    },

    computed: {
        filteredOrders() {
            if (!this.selectedDomain) {
                return [];
            }

            return this.orders.filter((order) => order.domain === this.selectedDomain);
        },
    },

    methods: {
        async loadOrders() {
            this.isLoading = true;
            this.loadError = null;

            try {
                const result = await this.lieferzeitenOrdersService.getOrders();
                this.orders = Array.isArray(result) ? result : [];
            } catch (error) {
                this.orders = [];
                this.loadError = error;
            } finally {
                this.isLoading = false;
            }
        },
    },
});
