import template from './lieferzeiten-index.html.twig';
import orders from '../../data/orders';

Shopware.Component.register('lieferzeiten-index', {
    template,

    data() {
        return {
            selectedDomain: null,
            orders,
        };
    },

    computed: {
        filteredOrders() {
            if (!this.selectedDomain) {
                return [];
            }

            return this.orders.filter((order) => order.domain === this.selectedDomain);
        },
    },
});
