import template from './lieferzeiten-statistics.html.twig';
import './lieferzeiten-statistics.scss';

const DAY_IN_MS = 24 * 60 * 60 * 1000;

Shopware.Component.register('lieferzeiten-statistics', {
    template,

    props: {
        orders: {
            type: Array,
            required: true,
        },
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
            tableColumns: [
                { property: 'orderNumber', label: this.$t('lieferzeiten.table.orderNumber'), primary: true },
                { property: 'domain', label: this.$t('lieferzeiten.table.domain') },
                { property: 'status', label: this.$t('lieferzeiten.table.status') },
                { property: 'promisedAt', label: this.$t('lieferzeiten.statistics.promisedDate') },
            ],
        };
    },

    computed: {
        channelOptions() {
            const channels = Array.from(new Set(this.orders.map((order) => order.domain)));
            return [
                { value: 'all', label: this.$t('lieferzeiten.statistics.allChannels') },
                ...channels.map((channel) => ({ value: channel, label: channel })),
            ];
        },

        referenceDate() {
            const timestamps = this.orders.map((order) => new Date(order.createdAt).getTime()).filter(Number.isFinite);

            if (!timestamps.length) {
                return new Date();
            }

            return new Date(Math.max(...timestamps));
        },

        filteredOrders() {
            const periodStart = new Date(this.referenceDate.getTime() - (this.selectedPeriod * DAY_IN_MS));

            return this.orders
                .filter((order) => {
                    const inSelectedDomain = !this.selectedDomain || order.domain === this.selectedDomain;
                    const inSelectedChannel = this.selectedChannel === 'all' || order.domain === this.selectedChannel;
                    const inPeriod = new Date(order.createdAt) >= periodStart;

                    return inSelectedDomain && inSelectedChannel && inPeriod;
                })
                .map((order) => {
                    const openParcels = order.parcels.filter((parcel) => !parcel.closed).length;
                    const overdue = new Date(order.promisedAt) < this.referenceDate && openParcels > 0;

                    return {
                        ...order,
                        status: overdue ? this.$t('lieferzeiten.statistics.status.overdue') : (openParcels > 0 ? this.$t('lieferzeiten.status.open') : this.$t('lieferzeiten.status.closed')),
                    };
                });
        },

        metrics() {
            const openCount = this.filteredOrders.filter((order) => order.status === this.$t('lieferzeiten.status.open')).length;
            const overdueCount = this.filteredOrders.filter((order) => order.status === this.$t('lieferzeiten.statistics.status.overdue')).length;

            return {
                open: openCount,
                overdue: overdueCount,
                activities: this.filteredOrders.length,
            };
        },

        channelChartData() {
            const grouped = this.filteredOrders.reduce((acc, order) => {
                if (!acc[order.domain]) {
                    acc[order.domain] = 0;
                }
                acc[order.domain] += 1;
                return acc;
            }, {});

            return Object.entries(grouped)
                .map(([channel, value]) => ({ channel, value }))
                .sort((a, b) => b.value - a.value);
        },

        maxChartValue() {
            if (!this.channelChartData.length) {
                return 1;
            }

            return Math.max(...this.channelChartData.map((item) => item.value));
        },
    },

    methods: {
        formatDate(dateString) {
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
                this.$t('lieferzeiten.statistics.promisedDate'),
            ];

            const rows = this.filteredOrders.map((order) => [
                order.orderNumber,
                order.domain,
                order.status,
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
