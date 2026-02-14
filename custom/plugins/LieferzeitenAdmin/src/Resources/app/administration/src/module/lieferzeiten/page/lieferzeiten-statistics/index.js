import template from './lieferzeiten-statistics.html.twig';
import './lieferzeiten-statistics.scss';
import { normalizeTimelinePoints } from './timeline.util';

Shopware.Component.register('lieferzeiten-statistics', {
    template,

    inject: [
        'lieferzeitenOrdersService',
    ],

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
            ],
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

        timelineSeries() {
            return normalizeTimelinePoints(this.statistics.timeline, {
                selectedChannel: this.selectedChannel,
                selectedDomain: this.selectedDomain,
            });
        },

        timelineMaxValue() {
            if (!this.timelineSeries.length) {
                return 0;
            }

            return Math.max(...this.timelineSeries.map((item) => item.value));
        },

        timelineChartPaths() {
            if (this.timelineSeries.length < 2) {
                return null;
            }

            const width = 100;
            const height = 60;
            const maxValue = this.timelineMaxValue || 1;
            const step = width / (this.timelineSeries.length - 1);
            const points = this.timelineSeries.map((item, index) => {
                const x = Number((index * step).toFixed(2));
                const normalized = Math.min(item.value / maxValue, 1);
                const y = Number((height - (normalized * height)).toFixed(2));

                return { x, y };
            });

            const linePath = points
                .map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`)
                .join(' ');
            const areaPath = `${linePath} L ${width} ${height} L 0 ${height} Z`;

            return { linePath, areaPath };
        },
    },

    methods: {
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

        formatTimelineLabel(dateString) {
            return Shopware.Utils.format.date(dateString);
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
