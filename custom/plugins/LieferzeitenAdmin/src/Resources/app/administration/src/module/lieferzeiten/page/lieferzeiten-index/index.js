import template from './lieferzeiten-index.html.twig';
import { normalizeDomainKey, resolveDomainKeyForSourceSystem } from '../../utils/domain-source-mapping';

const DOMAIN_SOURCE_MAP = {
    'first-medical-e-commerce': ['first medical', 'e-commerce', 'shopware', 'gambio', 'first-medical-e-commerce'],
    'medical-solutions': ['medical solutions', 'medical-solutions'],
};

const DOMAIN_LABEL_ALIASES = {
    'First Medical': 'first-medical-e-commerce',
    'E-Commerce': 'first-medical-e-commerce',
    'First Medical - E-Commerce': 'first-medical-e-commerce',
    'Medical Solutions': 'medical-solutions',
};

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
            statisticsMetrics: {
                openOrders: 0,
                overdueShipping: 0,
                overdueDelivery: 0,
            },
        };
    },

    created() {
        this.loadOrders();
        this.loadStatistics();
    },

    watch: {
        selectedDomain() {
            this.loadOrders();
            this.loadStatistics();
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

        async loadStatistics() {
            try {
                const payload = await this.lieferzeitenOrdersService.getStatistics({
                    period: 30,
                    domain: normalizeDomainKey(this.selectedDomain),
                    channel: 'all',
                });

                this.statisticsMetrics = {
                    openOrders: payload?.metrics?.openOrders ?? 0,
                    overdueShipping: payload?.metrics?.overdueShipping ?? 0,
                    overdueDelivery: payload?.metrics?.overdueDelivery ?? 0,
                };
            } catch (error) {
                this.statisticsMetrics = {
                    openOrders: 0,
                    overdueShipping: 0,
                    overdueDelivery: 0,
                };
            }
        },

        resolveOrderDomainKey(order) {
            const orderDomain = String(order?.domain || order?.sourceSystem || '').trim();
            if (orderDomain === '') {
                return null;
            }

            const aliasMatch = DOMAIN_LABEL_ALIASES[orderDomain];
            if (aliasMatch) {
                return aliasMatch;
            }

            const normalizedOrderDomain = orderDomain.toLowerCase();

            return Object.keys(DOMAIN_SOURCE_MAP).find((domainKey) => DOMAIN_SOURCE_MAP[domainKey]
                .map((source) => source.toLowerCase())
                .includes(normalizedOrderDomain)) || null;
        },
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
