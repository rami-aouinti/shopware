import template from './lieferzeiten-management-stats-page.html.twig';

Shopware.Component.register('lieferzeiten-management-stats-page', {
    template,
    inject: ['lieferzeitenStatsApiService'],

    data() {
        return {
            isLoading: false,
            stats: null,
        };
    },

    computed: {
        kpis() {
            return this.stats?.kpis ?? {};
        },

        rows() {
            return this.stats?.rows ?? [];
        },

        columns() {
            return [
                { property: 'metric', label: this.$t('lieferzeiten-management.stats.columnMetric') },
                { property: 'value', label: this.$t('lieferzeiten-management.stats.columnValue') },
            ];
        },

        formattedAverageLeadTime() {
            const value = this.kpis.averageLeadTimeDays;

            if (value === null || value === undefined) {
                return '-';
            }

            return this.$tc('lieferzeiten-management.stats.kpiAverageLeadTimeValue', Number(value));
        },

        formattedOverdueRate() {
            const value = this.kpis.overdueRate;

            if (value === null || value === undefined) {
                return '-';
            }

            return this.$tc('lieferzeiten-management.stats.kpiOverdueRateValue', Math.round(value * 100));
        },

        formattedVolume() {
            const value = this.kpis.volume;

            if (value === null || value === undefined) {
                return '-';
            }

            return this.$tc('lieferzeiten-management.stats.kpiVolumeValue', Number(value));
        },
    },

    created() {
        this.loadStats();
    },

    methods: {
        loadStats() {
            this.isLoading = true;

            this.lieferzeitenStatsApiService.getStats()
                .then((stats) => {
                    this.stats = stats;
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
