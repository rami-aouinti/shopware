import './test-admin-menu-page.scss';
import template from './test-admin-menu-page.html.twig';

const DAY_IN_MS = 24 * 60 * 60 * 1000;

function toDate(value) {
    return new Date(`${value}T00:00:00`);
}

Shopware.Component.register('test-admin-menu-page', {
    template,

    data() {
        const today = new Date();

        return {
            availableChannels: [
                { value: 'all', label: 'Alle Kanäle' },
                { value: 'storefront', label: 'Storefront' },
                { value: 'marketplace', label: 'Marketplace' },
                { value: 'retail', label: 'Retail' },
            ],
            availablePeriods: [
                { value: 7, label: 'Letzte 7 Tage' },
                { value: 30, label: 'Letzte 30 Tage' },
                { value: 90, label: 'Letzte 90 Tage' },
            ],
            selectedPeriod: 30,
            selectedChannel: 'all',
            selectedActivity: null,
            today,
            tableColumns: [
                { property: 'activity', label: 'Aktivität', allowResize: true, primary: true },
                { property: 'channel', label: 'Kanal', allowResize: true },
                { property: 'status', label: 'Status', allowResize: true },
                { property: 'dueDate', label: 'Fällig am', allowResize: true },
            ],
            activityRows: [
                { id: 1, activity: 'Bestellung #10001 prüfen', channel: 'storefront', status: 'offen', dueDate: '2026-01-30' },
                { id: 2, activity: 'Bestellung #10034 Versand klären', channel: 'marketplace', status: 'überfällig', dueDate: '2026-01-15' },
                { id: 3, activity: 'Retour #R-900 abstimmen', channel: 'retail', status: 'offen', dueDate: '2026-02-11' },
                { id: 4, activity: 'Rechnung #INV-333 freigeben', channel: 'storefront', status: 'geschlossen', dueDate: '2026-02-02' },
                { id: 5, activity: 'Bestellung #10035 klären', channel: 'marketplace', status: 'offen', dueDate: '2026-02-05' },
                { id: 6, activity: 'Versandfall #998 bearbeiten', channel: 'storefront', status: 'überfällig', dueDate: '2026-01-12' },
            ],
        };
    },

    computed: {
        filteredRows() {
            const periodStart = new Date(this.today.getTime() - (this.selectedPeriod * DAY_IN_MS));

            return this.activityRows.filter((row) => {
                const rowDate = toDate(row.dueDate);
                const isWithinPeriod = rowDate >= periodStart;
                const isInChannel = this.selectedChannel === 'all' || row.channel === this.selectedChannel;

                return isWithinPeriod && isInChannel;
            });
        },

        metrics() {
            const offen = this.filteredRows.filter((row) => row.status === 'offen').length;
            const overdue = this.filteredRows.filter((row) => row.status === 'überfällig').length;

            return {
                offen,
                overdue,
                activities: this.filteredRows.length,
            };
        },

        chartData() {
            const grouped = this.filteredRows.reduce((acc, row) => {
                const key = row.channel;

                if (!acc[key]) {
                    acc[key] = 0;
                }

                acc[key] += 1;

                return acc;
            }, {});

            return Object.entries(grouped)
                .map(([channel, value]) => ({ channel, value }))
                .sort((a, b) => b.value - a.value);
        },

        maxChartValue() {
            if (!this.chartData.length) {
                return 1;
            }

            return Math.max(...this.chartData.map((item) => item.value));
        },

        selectedActivityRows() {
            if (!this.selectedActivity) {
                return [];
            }

            return this.filteredRows.filter((row) => row.activity === this.selectedActivity.activity);
        },
    },

    methods: {
        getChannelLabel(channelValue) {
            const channel = this.availableChannels.find((item) => item.value === channelValue);
            return channel?.label || channelValue;
        },

        getBarWidth(value) {
            const ratio = Math.round((value / this.maxChartValue) * 100);
            return `${Math.max(ratio, 6)}%`;
        },

        onDrilldown(item) {
            this.selectedActivity = item;
        },

        closeDrilldown() {
            this.selectedActivity = null;
        },

        exportCsv() {
            const headers = ['Aktivität', 'Kanal', 'Status', 'Fällig am'];
            const rows = this.filteredRows.map((row) => [
                row.activity,
                this.getChannelLabel(row.channel),
                row.status,
                row.dueDate,
            ]);

            const csv = [headers, ...rows]
                .map((line) => line.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(';'))
                .join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = 'aktivitaeten.csv';
            link.click();

            URL.revokeObjectURL(url);
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.$t('test-admin-menu.general.title')),
        };
    },
});
