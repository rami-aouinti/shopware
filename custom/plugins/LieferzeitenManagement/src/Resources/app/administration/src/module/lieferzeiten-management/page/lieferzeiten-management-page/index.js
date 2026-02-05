import template from './lieferzeiten-management-page.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('lieferzeiten-management-page', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

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
            dateHistoryRepository: null,
            taskRepository: null,
            taskAssignmentRepository: null,
            settingsRepository: null,
            creatingTaskIds: {},
            selectedArea: null,
            selectedView: null,
            settingsByArea: {},
            areaOptions: [
                { value: 'first-medical', label: this.$t('lieferzeiten-management.general.areaFirstMedical') },
                { value: 'e-commerce', label: this.$t('lieferzeiten-management.general.areaECommerce') },
                { value: 'medical-solutions', label: this.$t('lieferzeiten-management.general.areaMedicalSolutions') },
            ],
            viewOptions: [
                { value: 'all', label: this.$t('lieferzeiten-management.general.viewAllOrders') },
                { value: 'open', label: this.$t('lieferzeiten-management.general.viewOpenOrders') },
            ],
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
                    property: 'businessStatusLabel',
                    label: this.$t('lieferzeiten-management.general.columnBusinessStatus'),
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
                {
                    property: 'actions',
                    label: this.$t('lieferzeiten-management.general.columnActions'),
                    align: 'center',
                },
            ];
        },

        needsSelection() {
            return !this.selectedArea || !this.selectedView;
        },

        kpiOpenOrdersTotal() {
            if (!this.items) {
                return 0;
            }

            return this.items.length;
        },

        kpiOverdueShipping() {
            if (!this.items) {
                return 0;
            }

            return this.items.filter((item) => this.isOverdue(item?.latestShippingAt, item?.shippedAt)).length;
        },

        kpiOverdueDelivery() {
            if (!this.items) {
                return 0;
            }

            return this.items.filter((item) => this.isOverdue(item?.latestDeliveryAt, item?.deliveredAt)).length;
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_package');
        this.trackingEventRepository = this.repositoryFactory.create('lieferzeiten_tracking_event');
        this.orderPositionRepository = this.repositoryFactory.create('lieferzeiten_order_position');
        this.dateHistoryRepository = this.repositoryFactory.create('lieferzeiten_date_history');
        this.taskRepository = this.repositoryFactory.create('lieferzeiten_task');
        this.taskAssignmentRepository = this.repositoryFactory.create('lieferzeiten_task_assignment');
        this.settingsRepository = this.repositoryFactory.create('lieferzeiten_settings');
        this.restoreSelection();
        this.loadAreaMappings().then(() => {
            if (!this.needsSelection) {
                this.loadPackages();
            }
        });
    },

    methods: {
        loadAreaMappings() {
            const criteria = new Criteria(1, 250);
            criteria.addAssociation('salesChannel');

            return this.settingsRepository.search(criteria, Shopware.Context.api).then((result) => {
                const mapping = {};
                result.forEach((setting) => {
                    if (!setting.area || !setting.salesChannelId) {
                        return;
                    }

                    if (!mapping[setting.area]) {
                        mapping[setting.area] = [];
                    }

                    mapping[setting.area].push(setting.salesChannelId);
                });

                this.settingsByArea = mapping;
            });
        },

        restoreSelection() {
            const stored = localStorage.getItem('lieferzeiten-management.selection');
            if (!stored) {
                return;
            }

            try {
                const parsed = JSON.parse(stored);
                this.selectedArea = parsed.selectedArea || null;
                this.selectedView = parsed.selectedView || null;
            } catch (error) {
                this.selectedArea = null;
                this.selectedView = null;
            }
        },

        persistSelection() {
            localStorage.setItem('lieferzeiten-management.selection', JSON.stringify({
                selectedArea: this.selectedArea,
                selectedView: this.selectedView,
            }));
        },

        onSelectionChange() {
            this.persistSelection();
            if (!this.needsSelection) {
                this.loadAreaMappings().then(() => {
                    this.onRefresh();
                });
            }
        },

        isOverdue(latestDate, completedDate) {
            if (!latestDate || completedDate) {
                return false;
            }

            return new Date(latestDate).getTime() < Date.now();
        },

        getRangeDays(startDate, endDate) {
            const start = new Date(startDate).setHours(0, 0, 0, 0);
            const end = new Date(endDate).setHours(0, 0, 0, 0);
            const diffMs = end - start;
            return Math.floor(diffMs / 86400000) + 1;
        },

        getWeekNumber(dateValue) {
            const date = new Date(dateValue);
            const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNumber = target.getUTCDay() || 7;
            target.setUTCDate(target.getUTCDate() + 4 - dayNumber);
            const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
            return Math.ceil((((target - yearStart) / 86400000) + 1) / 7);
        },

        validateRange(startDate, endDate, minDays, maxDays, labelKey) {
            if (!startDate || !endDate) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.general.validationRangeRequired', {
                        label: this.$t(labelKey),
                    }),
                });
                return false;
            }

            if (new Date(startDate).getTime() > new Date(endDate).getTime()) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.general.validationRangeOrder'),
                });
                return false;
            }

            const rangeDays = this.getRangeDays(startDate, endDate);
            if (rangeDays < minDays || rangeDays > maxDays) {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.general.validationRangeLength', {
                        label: this.$t(labelKey),
                        min: minDays,
                        max: maxDays,
                    }),
                });
                return false;
            }

            return true;
        },

        resolveAssignedUserId(item) {
            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('taskType', 'additional_delivery_request'));

            if (this.selectedArea) {
                criteria.addFilter(Criteria.equals('area', this.selectedArea));
            }

            if (item?.order?.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannelId', item.order.salesChannelId));
            }

            return this.taskAssignmentRepository.search(criteria, Shopware.Context.api).then((result) => {
                return result.first()?.assignedUserId || null;
            });
        },

        nextBusinessDay(date) {
            const next = new Date(date);
            next.setDate(next.getDate() + 1);
            while (next.getDay() === 0 || next.getDay() === 6) {
                next.setDate(next.getDate() + 1);
            }
            return next;
        },

        createAdditionalDeliveryRequest(item, position) {
            if (!item || !position) {
                return;
            }

            this.$set(this.creatingTaskIds, item.id, true);
            const currentUser = Shopware.State.get('session')?.currentUser;

            this.resolveAssignedUserId(item).then((assignedUserId) => {
                const task = this.taskRepository.create(Shopware.Context.api);
                task.type = 'additional_delivery_request';
                task.status = 'open';
                task.orderId = item.orderId || item.order?.id || null;
                task.packageId = item.id;
                task.orderPositionId = position.id;
                task.assignedUserId = assignedUserId;
                task.createdById = currentUser?.id || null;
                task.dueDate = this.nextBusinessDay(new Date()).toISOString();

                return this.taskRepository.save(task, Shopware.Context.api);
            }).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.general.taskCreateSuccess'),
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.general.taskCreateError'),
                });
            }).finally(() => {
                this.$set(this.creatingTaskIds, item.id, false);
            });
        },

        buildCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('order');
            criteria.addAssociation('order.orderCustomer');
            criteria.addAssociation('order.stateMachineState');
            criteria.addAssociation('order.transactions.paymentMethod');
            criteria.addAssociation('trackingNumbers');
            criteria.addAssociation('packagePositions.orderPosition');
            criteria.addAssociation('newDeliveryUpdatedBy');
            criteria.addFilter(Criteria.not('OR', [
                Criteria.contains('order.orderNumber', 'TEST'),
                Criteria.contains('order.orderCustomer.email', 'test'),
            ]));

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

            if (this.selectedView === 'open') {
                criteria.addFilter(Criteria.equalsAny('order.stateMachineState.technicalName', ['open', 'in_progress']));
            }

            if (this.selectedArea) {
                const salesChannelIds = this.settingsByArea[this.selectedArea] || [];
                if (salesChannelIds.length) {
                    criteria.addFilter(Criteria.equalsAny('order.salesChannelId', salesChannelIds));
                } else {
                    criteria.addFilter(Criteria.equals('order.salesChannelId', null));
                }
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
            if (this.needsSelection) {
                this.items = null;
                this.total = 0;
                return;
            }

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

        formatBusinessStatus(item) {
            if (!item) {
                return '-';
            }

            const label = item.businessStatusLabel ?? '';
            const code = item.businessStatusCode ?? '';

            if (label && code !== '') {
                return `${label} (${code})`;
            }

            if (label) {
                return label;
            }

            if (code !== '') {
                return String(code);
            }

            return '-';
        },

        onNewDeliveryChange(item) {
            if (!this.validateRange(
                item.newDeliveryStart,
                item.newDeliveryEnd,
                1,
                4,
                'lieferzeiten-management.general.columnNewDelivery',
            )) {
                return;
            }

            const currentUser = Shopware.State.get('session')?.currentUser;
            item.newDeliveryUpdatedAt = new Date().toISOString();
            item.newDeliveryUpdatedById = currentUser?.id || null;
            this.repository.save(item, Shopware.Context.api).then(() => {
                const historyEntry = this.dateHistoryRepository.create(Shopware.Context.api);
                historyEntry.packageId = item.id;
                historyEntry.type = 'new_delivery';
                historyEntry.rangeStart = item.newDeliveryStart;
                historyEntry.rangeEnd = item.newDeliveryEnd;
                historyEntry.comment = item.newDeliveryComment || null;
                historyEntry.createdById = currentUser?.id || null;
                this.dateHistoryRepository.save(historyEntry, Shopware.Context.api);

                const startWeek = this.getWeekNumber(item.newDeliveryStart);
                const endWeek = this.getWeekNumber(item.newDeliveryEnd);
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.general.saveNewDeliverySuccess', {
                        startWeek,
                        endWeek,
                    }),
                });
            });
        },

        onSupplierDeliveryChange(position) {
            if (!this.validateRange(
                position.supplierDeliveryStart,
                position.supplierDeliveryEnd,
                1,
                14,
                'lieferzeiten-management.general.columnSupplierDelivery',
            )) {
                return;
            }

            const currentUser = Shopware.State.get('session')?.currentUser;
            position.supplierDeliveryUpdatedAt = new Date().toISOString();
            position.supplierDeliveryUpdatedById = currentUser?.id || null;
            this.orderPositionRepository.save(position, Shopware.Context.api).then(() => {
                const historyEntry = this.dateHistoryRepository.create(Shopware.Context.api);
                historyEntry.orderPositionId = position.id;
                historyEntry.type = 'supplier_delivery';
                historyEntry.rangeStart = position.supplierDeliveryStart;
                historyEntry.rangeEnd = position.supplierDeliveryEnd;
                historyEntry.comment = position.supplierDeliveryComment || null;
                historyEntry.createdById = currentUser?.id || null;
                this.dateHistoryRepository.save(historyEntry, Shopware.Context.api);

                this.closeAdditionalDeliveryTasks(position.id);

                const startWeek = this.getWeekNumber(position.supplierDeliveryStart);
                const endWeek = this.getWeekNumber(position.supplierDeliveryEnd);
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.general.saveSupplierDeliverySuccess', {
                        startWeek,
                        endWeek,
                    }),
                });
            });
        },

        closeAdditionalDeliveryTasks(orderPositionId) {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('orderPositionId', orderPositionId));
            criteria.addFilter(Criteria.equals('type', 'additional_delivery_request'));
            criteria.addFilter(Criteria.equals('status', 'open'));

            this.taskRepository.search(criteria, Shopware.Context.api).then((result) => {
                if (!result.length) {
                    return;
                }

                const now = new Date().toISOString();
                const payload = result.map((task) => ({
                    id: task.id,
                    status: 'completed',
                    completedAt: now,
                }));

                return this.taskRepository.saveAll(payload, Shopware.Context.api);
            });
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
