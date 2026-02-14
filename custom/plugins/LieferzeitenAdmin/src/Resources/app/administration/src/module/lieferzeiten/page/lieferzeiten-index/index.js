import template from './lieferzeiten-index.html.twig';

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
            if (!this.selectedDomain) {
                return this.orders;
            }

            return this.orders.filter((order) => this.resolveOrderDomainKey(order) === this.selectedDomain);
        },
    },

    methods: {

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
                const result = await this.lieferzeitenOrdersService.getOrders({
                    domain: this.selectedDomain,
                });
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
