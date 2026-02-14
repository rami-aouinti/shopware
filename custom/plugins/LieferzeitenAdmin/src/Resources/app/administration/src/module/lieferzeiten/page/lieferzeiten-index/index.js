import template from './lieferzeiten-index.html.twig';
import { normalizeDomainKey, resolveDomainKeyForSourceSystem } from '../../utils/domain-source-mapping';

const DOMAIN_SOURCE_MAP = {
    'first-medical-shop.de': ['first-medical-shop.de', 'first medical', 'e-commerce', 'shopware', 'gambio', 'first-medical-e-commerce'],
    'ebay.de': ['ebay.de', 'ebay de'],
    'ebay.at': ['ebay.at', 'ebay at'],
    kaufland: ['kaufland'],
    peg: ['peg'],
    zonami: ['zonami'],
    'medical-solutions-germany.de': ['medical-solutions-germany.de', 'medical solutions', 'medical-solutions'],
};

const DOMAIN_LABEL_ALIASES = {
    'First Medical': 'first-medical-shop.de',
    'E-Commerce': 'first-medical-shop.de',
    'First Medical - E-Commerce': 'first-medical-shop.de',
    'Medical Solutions': 'medical-solutions-germany.de',
};

Shopware.Component.register('lieferzeiten-index', {
    template,

    inject: [
        'lieferzeitenOrdersService',
    ],

    data() {
        return {
            selectedDomain: null,
            selectedGroup: null,
            orders: [],
            isLoading: false,
            isStatisticsLoading: false,
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
            if (!this.canAccessMainViews) {
                return;
            }

            this.loadOrders();
            this.loadStatistics();
        },
    },

    computed: {
        canAccessMainViews() {
            return Boolean(this.selectedGroup);
        },

        filteredOrders() {
            const domainKey = normalizeDomainKey(this.selectedDomain);
            if (!domainKey) {
                return [];
            }

            return this.orders.filter((order) => order.domainKey === domainKey);
        },
    },

    methods: {

        onGroupChange(group) {
            this.selectedGroup = group;

            if (!this.canAccessMainViews) {
                this.orders = [];
                this.statisticsMetrics = {
                    openOrders: 0,
                    overdueShipping: 0,
                    overdueDelivery: 0,
                };
                return;
            }

            this.loadOrders();
            this.loadStatistics();
        },

        async loadStatistics() {
            if (!this.canAccessMainViews) {
                this.statisticsMetrics = {
                    openOrders: 0,
                    overdueShipping: 0,
                    overdueDelivery: 0,
                };
                return;
            }
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

        async reloadData() {
            await Promise.all([
                this.loadOrders(),
                this.loadStatistics(),
            ]);
        },

        async loadOrders() {
            if (!this.canAccessMainViews) {
                this.orders = [];
                this.isLoading = false;
                return;
            }
            this.isLoading = true;
            this.loadError = null;

            try {
                const domainKey = normalizeDomainKey(this.selectedDomain);
                const result = await this.lieferzeitenOrdersService.getOrders({
                    ...this.buildFilterParams(),
                    domain: domainKey,
                });
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

        buildFilterParams() {
            return Object.entries(this.filters).reduce((params, [key, value]) => {
                if (value === null || value === undefined) {
                    return params;
                }

                const normalizedValue = typeof value === 'string' ? value.trim() : value;
                if (normalizedValue === '') {
                    return params;
                }

                params[key] = normalizedValue;

                return params;
            }, {});
        },

        applyFilters() {
            this.loadOrders();
        },

        resetFilters() {
            this.filters = {
                bestellnummer: '',
                san6: '',
                shippingDateFrom: null,
                shippingDateTo: null,
                businessDateFrom: null,
                businessDateTo: null,
                deliveryDateFrom: null,
                deliveryDateTo: null,
                businessDateEndFrom: null,
                businessDateEndTo: null,
                lieferterminLieferantFrom: null,
                lieferterminLieferantTo: null,
                neuerLieferterminFrom: null,
                neuerLieferterminTo: null,
                user: '',
                sendenummer: '',
                status: '',
            };

            this.loadOrders();
        },
    },
});
