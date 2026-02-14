import template from './lieferzeiten-index.html.twig';
import { normalizeDomainKey, resolveDomainKeyForSourceSystem } from '../../utils/domain-source-mapping';

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

    watch: {
        selectedDomain() {
            this.loadOrders();
        },
    },

    computed: {
        filteredOrders() {
            const domainKey = normalizeDomainKey(this.selectedDomain);
            if (!domainKey) {
                return [];
            }

            return this.orders.filter((order) => order.domainKey === domainKey);
        },
    },

    methods: {
        async loadOrders() {
            this.isLoading = true;
            this.loadError = null;

            try {
                const domainKey = normalizeDomainKey(this.selectedDomain);
                const result = await this.lieferzeitenOrdersService.getOrders({ domain: domainKey });
                const orders = Array.isArray(result) ? result : [];

                this.orders = orders.map((order) => ({
                    ...order,
                    domainKey: resolveDomainKeyForSourceSystem(order.sourceSystem || order.domain),
                }));
            } catch (error) {
                this.orders = [];
                this.loadError = error;
            } finally {
                this.isLoading = false;
            }
        },
    },
});
