import template from './lieferzeiten-management-page.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('lieferzeiten-management-page', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            repository: null,
            trackingEventRepository: null,
            items: null,
            total: 0,
            isLoading: false,
            page: 1,
            limit: 25,
            orderNumberFilter: '',
            san6PackageFilter: '',
            trackingFilter: '',
            packageStatusFilter: '',
            shippedFrom: null,
            shippedTo: null,
            deliveredFrom: null,
            deliveredTo: null,
            isTrackingModalOpen: false,
            trackingEvents: null,
            trackingModalNumber: '',
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
                    property: 'order.orderNumber',
                    label: this.$t('lieferzeiten-management.general.columnOrderNumber'),
                },
                {
                    property: 'order.orderDateTime',
                    label: this.$t('lieferzeiten-management.general.columnOrderDate'),
                },
                {
                    property: 'order.orderCustomer.firstName',
                    label: this.$t('lieferzeiten-management.general.columnCustomerName'),
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
                    property: 'latestShippingAt',
                    label: this.$t('lieferzeiten-management.general.columnLatestShippingAt'),
                },
                {
                    property: 'latestDeliveryAt',
                    label: this.$t('lieferzeiten-management.general.columnLatestDeliveryAt'),
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
        this.trackingEventRepository = this.repositoryFactory.create('lieferzeiten_tracking_event');
        this.loadPackages();
    },

    methods: {
        buildCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('order');
            criteria.addAssociation('order.orderCustomer');
            criteria.addAssociation('trackingNumbers');

            if (this.orderNumberFilter) {
                criteria.addFilter(Criteria.contains('order.orderNumber', this.orderNumberFilter));
            }

            if (this.san6PackageFilter) {
                criteria.addFilter(Criteria.contains('san6PackageNumber', this.san6PackageFilter));
            }

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

        openTrackingModal(item) {
            const trackingNumber = item?.trackingNumber;
            const trackingNumberId = item?.trackingNumbers?.[0]?.id;

            this.trackingModalNumber = trackingNumber || '';
            this.isTrackingModalOpen = true;

            if (!trackingNumberId) {
                this.trackingEvents = [];
                return;
            }

            const criteria = new Criteria(1, 50);
            criteria.addFilter(Criteria.equals('trackingNumberId', trackingNumberId));
            criteria.addSorting(Criteria.sort('occurredAt', 'DESC'));

            this.trackingEventRepository.search(criteria, Shopware.Context.api).then((result) => {
                this.trackingEvents = result;
            });
        },

        closeTrackingModal() {
            this.isTrackingModalOpen = false;
            this.trackingEvents = null;
            this.trackingModalNumber = '';
        },
    },
});
