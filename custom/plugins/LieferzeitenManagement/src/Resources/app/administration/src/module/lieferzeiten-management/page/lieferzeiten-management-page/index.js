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
            san6OrderNumberFilter: '',
            trackingFilter: '',
            packageStatusFilter: '',
            orderStatusFilter: '',
            shippedFrom: null,
            shippedTo: null,
            deliveredFrom: null,
            deliveredTo: null,
            isTrackingModalOpen: false,
            trackingEvents: null,
            trackingModalNumber: '',
            orderPositionRepository: null,
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
                    property: 'paymentInfo',
                    label: this.$t('lieferzeiten-management.general.columnPayment'),
                },
                {
                    property: 'orderStatus',
                    label: this.$t('lieferzeiten-management.general.columnOrderStatus'),
                },
                {
                    property: 'san6OrderNumber',
                    label: this.$t('lieferzeiten-management.general.columnSan6OrderNumber'),
                },
                {
                    property: 'san6PositionNumber',
                    label: this.$t('lieferzeiten-management.general.columnSan6PositionNumber'),
                },
                {
                    property: 'quantity',
                    label: this.$t('lieferzeiten-management.general.columnQuantity'),
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
                {
                    property: 'supplierDelivery',
                    label: this.$t('lieferzeiten-management.general.columnSupplierDelivery'),
                },
                {
                    property: 'supplierComment',
                    label: this.$t('lieferzeiten-management.general.columnSupplierComment'),
                },
                {
                    property: 'newDelivery',
                    label: this.$t('lieferzeiten-management.general.columnNewDelivery'),
                },
                {
                    property: 'newDeliveryComment',
                    label: this.$t('lieferzeiten-management.general.columnNewDeliveryComment'),
                },
                {
                    property: 'deliveryUpdatedBy',
                    label: this.$t('lieferzeiten-management.general.columnUpdatedBy'),
                },
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_package');
        this.trackingEventRepository = this.repositoryFactory.create('lieferzeiten_tracking_event');
        this.orderPositionRepository = this.repositoryFactory.create('lieferzeiten_order_position');
        this.loadPackages();
    },

    methods: {
        buildCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('order');
            criteria.addAssociation('order.orderCustomer');
            criteria.addAssociation('order.stateMachineState');
            criteria.addAssociation('order.transactions.paymentMethod');
            criteria.addAssociation('trackingNumbers');
            criteria.addAssociation('packagePositions.orderPosition');
            criteria.addAssociation('newDeliveryUpdatedBy');

            if (this.orderNumberFilter) {
                criteria.addFilter(Criteria.contains('order.orderNumber', this.orderNumberFilter));
            }

            if (this.san6PackageFilter) {
                criteria.addFilter(Criteria.contains('san6PackageNumber', this.san6PackageFilter));
            }

            if (this.san6OrderNumberFilter) {
                criteria.addFilter(Criteria.contains('packagePositions.orderPosition.san6OrderNumber', this.san6OrderNumberFilter));
            }

            if (this.trackingFilter) {
                criteria.addFilter(Criteria.contains('trackingNumber', this.trackingFilter));
            }

            if (this.packageStatusFilter) {
                criteria.addFilter(Criteria.contains('packageStatus', this.packageStatusFilter));
            }

            if (this.orderStatusFilter) {
                criteria.addFilter(Criteria.contains('order.stateMachineState.name', this.orderStatusFilter));
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

        getOrderPositions(item) {
            if (!item?.packagePositions) {
                return [];
            }

            return item.packagePositions
                .map((position) => position.orderPosition)
                .filter((position) => position);
        },

        getLatestTransaction(order) {
            if (!order?.transactions?.length) {
                return null;
            }

            return [...order.transactions].sort((a, b) => {
                const aDate = a.createdAt ? new Date(a.createdAt).getTime() : 0;
                const bDate = b.createdAt ? new Date(b.createdAt).getTime() : 0;
                return bDate - aDate;
            })[0];
        },

        formatPaymentInfo(order) {
            const transaction = this.getLatestTransaction(order);
            if (!transaction) {
                return '-';
            }

            const method = transaction.paymentMethod?.name || '-';
            const paidAt = transaction.paidAt ? this.$d(new Date(transaction.paidAt)) : '-';
            return `${method} • ${paidAt}`;
        },

        onNewDeliveryChange(item) {
            const currentUser = Shopware.State.get('session')?.currentUser;
            item.newDeliveryUpdatedAt = new Date().toISOString();
            item.newDeliveryUpdatedById = currentUser?.id || null;
            this.repository.save(item, Shopware.Context.api);
        },

        onSupplierDeliveryChange(position) {
            const currentUser = Shopware.State.get('session')?.currentUser;
            position.supplierDeliveryUpdatedAt = new Date().toISOString();
            position.supplierDeliveryUpdatedById = currentUser?.id || null;
            this.orderPositionRepository.save(position, Shopware.Context.api);
        },

        getUserLabel(user) {
            if (!user) {
                return '-';
            }

            return `${user.firstName || ''} ${user.lastName || ''}`.trim();
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
