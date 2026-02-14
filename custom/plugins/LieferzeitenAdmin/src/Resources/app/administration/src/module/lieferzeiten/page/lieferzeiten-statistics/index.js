import template from './lieferzeiten-statistics.html.twig';
import './lieferzeiten-statistics.scss';

const TASK_CLOSABLE_STATUSES = ['open', 'in_progress', 'reopened'];
const TASK_REOPENABLE_STATUSES = ['done', 'cancelled'];

Shopware.Component.register('lieferzeiten-statistics', {
    template,

    inject: [
        'lieferzeitenOrdersService',
        'lieferzeitenTrackingService',
        'acl',
    ],

    mixins: ['notification'],

    props: {
        selectedDomain: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            periodOptions: [
                { value: 7, label: this.$t('lieferzeiten.statistics.period.7') },
                { value: 30, label: this.$t('lieferzeiten.statistics.period.30') },
                { value: 90, label: this.$t('lieferzeiten.statistics.period.90') },
            ],
            selectedPeriod: 30,
            selectedChannel: 'all',
            selectedActivity: null,
            isLoading: false,
            loadError: null,
            statistics: {
                metrics: {
                    openOrders: 0,
                    overdueShipping: 0,
                    overdueDelivery: 0,
                },
                channels: [],
                timeline: [],
                activitiesData: [],
            },
            tableColumns: [
                { property: 'orderNumber', label: this.$t('lieferzeiten.table.orderNumber'), primary: true },
                { property: 'domain', label: this.$t('lieferzeiten.table.domain') },
                { property: 'status', label: this.$t('lieferzeiten.table.status') },
                { property: 'eventAt', label: this.$t('lieferzeiten.statistics.activityDate') },
                { property: 'promisedAt', label: this.$t('lieferzeiten.statistics.promisedDate') },
                { property: 'actions', label: this.$t('lieferzeiten.statistics.actionsColumn') },
            ],
            actionLoadingByActivity: {},
        };
    },

    created() {
        this.loadStatistics();
    },

    watch: {
        selectedPeriod() {
            this.loadStatistics();
        },
        selectedChannel() {
            this.loadStatistics();
        },
        selectedDomain() {
            this.selectedChannel = 'all';
            this.loadStatistics();
        },
    },

    computed: {
        channelOptions() {
            return [
                { value: 'all', label: this.$t('lieferzeiten.statistics.allChannels') },
                ...this.statistics.channels.map((item) => ({ value: item.channel, label: item.channel })),
            ];
        },

        metrics() {
            return {
                open: this.statistics.metrics.openOrders,
                overdueShipping: this.statistics.metrics.overdueShipping,
                overdueDelivery: this.statistics.metrics.overdueDelivery,
            };
        },

        filteredOrders() {
            return this.statistics.activitiesData.map((activity) => ({
                ...activity,
                status: activity.status === 'open'
                    ? this.$t('lieferzeiten.status.open')
                    : (activity.status === 'done' ? this.$t('lieferzeiten.status.closed') : activity.status),
            }));
        },

        channelChartData() {
            return this.statistics.channels;
        },

        maxChartValue() {
            if (!this.channelChartData.length) {
                return 1;
            }

            return Math.max(...this.channelChartData.map((item) => item.value));
        },
    },

    methods: {
        hasViewAccess() {
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.viewer') || this.acl.can('admin');
        },

        hasEditAccess() {
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.editor') || this.acl.can('admin');
        },

        resolveActivityActions(item) {
            const actions = [];
            const eventType = String(item?.eventType || '').toLowerCase();

            if (['paket', 'position', 'audit'].includes(eventType)) {
                actions.push(this.createOrderAction(item));
            }

            if (eventType === 'task') {
                actions.push(this.createTaskRouteAction(item));
                actions.push(this.createTaskTransitionAction(item));
            }

            if (item?.trackingNumber) {
                actions.push(this.createTrackingAction(item));
            }

            if (actions.length === 0) {
                return [{
                    id: 'unavailable',
                    label: this.$t('lieferzeiten.statistics.actionsUnavailable'),
                    icon: 'regular-ban',
                    disabled: true,
                    disabledReason: this.$t('lieferzeiten.statistics.actionUnavailableReason'),
                    onClick: null,
                }];
            }

            return actions;
        },

        createOrderAction(item) {
            const hasOrderReference = Boolean(item?.paketId || item?.orderNumber);

            return {
                id: 'open-order',
                label: this.$t('lieferzeiten.statistics.actions.openOrder'),
                icon: 'regular-shopping-bag',
                disabled: !this.hasViewAccess() || !hasOrderReference,
                disabledReason: !this.hasViewAccess()
                    ? this.$t('lieferzeiten.statistics.actionReasons.noViewAcl')
                    : this.$t('lieferzeiten.statistics.actionReasons.missingOrderReference'),
                onClick: () => this.navigateToOrder(item),
            };
        },

        createTaskRouteAction(item) {
            return {
                id: 'open-task',
                label: this.$t('lieferzeiten.statistics.actions.openTask'),
                icon: 'regular-briefcase',
                disabled: !this.hasViewAccess() || !item?.taskId,
                disabledReason: !this.hasViewAccess()
                    ? this.$t('lieferzeiten.statistics.actionReasons.noViewAcl')
                    : this.$t('lieferzeiten.statistics.actionReasons.missingTaskReference'),
                onClick: () => this.navigateToTask(item),
            };
        },

        createTaskTransitionAction(item) {
            const normalizedStatus = String(item?.status || '').toLowerCase();
            const isClosable = TASK_CLOSABLE_STATUSES.includes(normalizedStatus);
            const isReopenable = TASK_REOPENABLE_STATUSES.includes(normalizedStatus);

            const operation = isReopenable ? 'reopen' : 'close';
            const isActionAvailable = isClosable || isReopenable;

            return {
                id: `task-${operation}`,
                label: this.$t(`lieferzeiten.statistics.actions.${operation}Task`),
                icon: isReopenable ? 'regular-redo' : 'regular-checkmark',
                disabled: !this.hasEditAccess() || !item?.taskId || !isActionAvailable,
                disabledReason: !this.hasEditAccess()
                    ? this.$t('lieferzeiten.statistics.actionReasons.noEditAcl')
                    : (!item?.taskId
                        ? this.$t('lieferzeiten.statistics.actionReasons.missingTaskReference')
                        : this.$t('lieferzeiten.statistics.actionReasons.unsupportedTaskStatus')),
                onClick: () => this.performTaskAction(item, operation),
                isLoading: Boolean(this.actionLoadingByActivity[this.getActionLoadingKey(item, operation)]),
            };
        },

        createTrackingAction(item) {
            return {
                id: 'open-tracking',
                label: this.$t('lieferzeiten.statistics.actions.openTracking'),
                icon: 'regular-shipping',
                disabled: !this.hasViewAccess() || !item?.trackingNumber,
                disabledReason: !this.hasViewAccess()
                    ? this.$t('lieferzeiten.statistics.actionReasons.noViewAcl')
                    : this.$t('lieferzeiten.statistics.actionReasons.missingTrackingReference'),
                onClick: () => this.navigateToTracking(item),
            };
        },

        getActionLoadingKey(item, operation) {
            return `${item?.id || 'unknown'}:${operation}`;
        },

        async performTaskAction(item, operation) {
            if (!item?.taskId || !['close', 'reopen'].includes(operation)) {
                return;
            }

            const loadingKey = this.getActionLoadingKey(item, operation);
            this.$set(this.actionLoadingByActivity, loadingKey, true);

            try {
                await this.lieferzeitenOrdersService.post(`tasks/${encodeURIComponent(item.taskId)}/${operation}`, {});
                this.createNotificationSuccess({
                    title: this.$t('lieferzeiten.statistics.actionSuccessTitle'),
                    message: this.$t(`lieferzeiten.statistics.actionSuccess.${operation}Task`),
                });
                await this.loadStatistics();
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('lieferzeiten.statistics.actionErrorTitle'),
                    message: this.$t(`lieferzeiten.statistics.actionError.${operation}Task`),
                });
            } finally {
                this.$delete(this.actionLoadingByActivity, loadingKey);
            }
        },

        navigateToOrder(item) {
            this.$router.push({
                name: 'lieferzeiten.index',
                query: {
                    bestellnummer: item?.orderNumber || undefined,
                    paketId: item?.paketId || undefined,
                },
            });
        },

        navigateToTask(item) {
            this.$router.push({
                name: 'lieferzeiten.index',
                query: {
                    taskId: item?.taskId || undefined,
                    bestellnummer: item?.orderNumber || undefined,
                },
            });
        },

        async navigateToTracking(item) {
            const trackingNumber = String(item?.trackingNumber || '').trim();
            if (trackingNumber === '') {
                return;
            }

            try {
                await this.lieferzeitenTrackingService.history('dhl', trackingNumber);
            } catch (error) {
                this.createNotificationError({
                    title: this.$t('lieferzeiten.statistics.actionErrorTitle'),
                    message: this.$t('lieferzeiten.statistics.actionError.openTracking'),
                });
                return;
            }

            window.open(`https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=${encodeURIComponent(trackingNumber)}`, '_blank', 'noopener');
        },

        actionTooltip(action) {
            if (!action?.disabled) {
                return '';
            }

            return action.disabledReason || this.$t('lieferzeiten.statistics.actionUnavailableReason');
        },

        async loadStatistics() {
            this.isLoading = true;
            this.loadError = null;

            try {
                const payload = await this.lieferzeitenOrdersService.getStatistics({
                    period: this.selectedPeriod,
                    domain: this.selectedDomain,
                    channel: this.selectedChannel,
                });

                this.statistics = {
                    metrics: payload.metrics ?? {
                        openOrders: 0,
                        overdueShipping: 0,
                        overdueDelivery: 0,
                    },
                    channels: Array.isArray(payload.channels) ? payload.channels : [],
                    timeline: Array.isArray(payload.timeline) ? payload.timeline : [],
                    activitiesData: Array.isArray(payload.activitiesData) ? payload.activitiesData : [],
                };
            } catch (error) {
                this.statistics = {
                    metrics: {
                        openOrders: 0,
                        overdueShipping: 0,
                        overdueDelivery: 0,
                    },
                    channels: [],
                    timeline: [],
                    activitiesData: [],
                };
                this.loadError = error;
            } finally {
                this.isLoading = false;
            }
        },

        formatDate(dateString) {
            if (!dateString) {
                return '—';
            }

            return Shopware.Utils.format.date(dateString);
        },

        getChartBarWidth(value) {
            return `${Math.max(Math.round((value / this.maxChartValue) * 100), 6)}%`;
        },

        onDrilldown(item) {
            this.selectedActivity = item;
        },

        closeDrilldown() {
            this.selectedActivity = null;
        },

        exportCsv() {
            const headers = [
                this.$t('lieferzeiten.table.orderNumber'),
                this.$t('lieferzeiten.table.domain'),
                this.$t('lieferzeiten.table.status'),
                this.$t('lieferzeiten.statistics.activityDate'),
                this.$t('lieferzeiten.statistics.promisedDate'),
            ];

            const rows = this.filteredOrders.map((order) => [
                order.orderNumber,
                order.domain,
                order.status,
                this.formatDate(order.eventAt),
                this.formatDate(order.promisedAt),
            ]);

            const csv = [headers, ...rows]
                .map((line) => line.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(';'))
                .join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');

            link.href = URL.createObjectURL(blob);
            link.download = 'lieferzeiten-statistiken.csv';
            link.click();

            URL.revokeObjectURL(link.href);
        },
    },
});
