import template from './lieferzeiten-management-page.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('lieferzeiten-management-page', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            repository: null,
            items: null,
            total: 0,
            isLoading: false,
            page: 1,
            limit: 25,
            trackingFilter: '',
            packageStatusFilter: '',
            shippedFrom: null,
            shippedTo: null,
            deliveredFrom: null,
            deliveredTo: null,
        };
    },

    computed: {
        columns() {
            return [
                {
                    property: 'san6PackageNumber',
                    label: this.$t('lieferzeiten-management.general.columnSan6PackageNumber'),
                    primary: true,
                    allowResize: true,
                },
                {
                    property: 'packageStatus',
                    label: this.$t('lieferzeiten-management.general.columnPackageStatus'),
                },
                {
                    property: 'shippedAt',
                    label: this.$t('lieferzeiten-management.general.columnShippedAt'),
                },
                {
                    property: 'deliveredAt',
                    label: this.$t('lieferzeiten-management.general.columnDeliveredAt'),
                },
                {
                    property: 'trackingNumber',
                    label: this.$t('lieferzeiten-management.general.columnTrackingNumber'),
                },
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_package');
        this.loadPackages();
    },

    methods: {
        buildCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.trackingFilter) {
                criteria.addFilter(Criteria.contains('trackingNumber', this.trackingFilter));
            }

            if (this.packageStatusFilter) {
                criteria.addFilter(Criteria.contains('packageStatus', this.packageStatusFilter));
            }

            if (this.shippedFrom || this.shippedTo) {
                criteria.addFilter(Criteria.range('shippedAt', {
                    gte: this.shippedFrom || undefined,
                    lte: this.shippedTo || undefined,
                }));
            }

            if (this.deliveredFrom || this.deliveredTo) {
                criteria.addFilter(Criteria.range('deliveredAt', {
                    gte: this.deliveredFrom || undefined,
                    lte: this.deliveredTo || undefined,
                }));
            }

            return criteria;
        },

        loadPackages() {
            this.isLoading = true;
            const criteria = this.buildCriteria();

            this.repository.search(criteria, Shopware.Context.api).then((result) => {
                this.items = result;
                this.total = result.total;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onRefresh() {
            this.page = 1;
            this.loadPackages();
        },

        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.loadPackages();
        },
    },
});
