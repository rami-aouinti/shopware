import template from './lieferzeiten-channel-settings-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-channel-settings-list', {
    template,

    mixins: ['notification'],

    inject: ['repositoryFactory', 'lieferzeitenOrdersService', 'acl'],

    data() {
        return {
            salesChannelRepository: null,
            salesChannels: [],
            selectedSalesChannelId: null,
            isLoading: false,
            isSeedingDemoData: false,
            hasDemoData: false,
            pdmsPayload: null,
        };
    },

    computed: {
        hasEditAccess() {
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.editor') || this.acl.can('admin');
        },

        canLoadPdmsLieferzeiten() {
            return typeof this.selectedSalesChannelId === 'string' && this.selectedSalesChannelId.length > 0;
        },
    },

    created() {
        this.salesChannelRepository = this.repositoryFactory.create('sales_channel');
        this.loadSalesChannels();
        this.loadDemoDataStatus();
    },

    methods: {
        extractErrorMessage(error) {
            return error?.response?.data?.errors?.[0]?.detail
                || error?.response?.data?.message
                || error?.message
                || this.$tc('global.default.error');
        },

        notifyRequestError(error, fallbackTitle) {
            this.createNotificationError({
                title: fallbackTitle,
                message: this.extractErrorMessage(error),
            });
        },

        async loadSalesChannels() {
            this.isLoading = true;

            try {
                const criteria = new Criteria(1, 250);
                criteria.addSorting(Criteria.sort('name', 'ASC'));

                const result = await this.salesChannelRepository.search(criteria, Shopware.Context.api);
                this.salesChannels = result;

                if (!this.selectedSalesChannelId && this.salesChannels.length > 0) {
                    this.selectedSalesChannelId = this.salesChannels[0].id;
                    await this.loadPdmsLieferzeiten();
                }
            } catch (error) {
                this.notifyRequestError(error, 'Sales Channels');
            } finally {
                this.isLoading = false;
            }
        },

        async loadDemoDataStatus() {
            try {
                const response = await this.lieferzeitenOrdersService.getDemoDataStatus();
                this.hasDemoData = Boolean(response?.hasDemoData);
            } catch (error) {
                this.notifyRequestError(error, 'DemoDaten');
            }
        },

        async onToggleDemoData() {
            if (!this.hasEditAccess) {
                return;
            }

            this.isSeedingDemoData = true;

            try {
                const response = await this.lieferzeitenOrdersService.toggleDemoData();
                this.hasDemoData = Boolean(response?.hasDemoData);

                if (response?.action === 'removed') {
                    const deleted = response?.deleted || {};
                    const totalDeleted = Object.values(deleted).reduce((sum, value) => sum + Number(value || 0), 0);
                    this.createNotificationSuccess({
                        title: 'DemoDaten',
                        message: totalDeleted > 0 ? `Entfernt (${totalDeleted} Datensätze).` : 'Keine Demo-Daten vorhanden.',
                    });

                    return;
                }

                const created = response?.created || {};
                const totalCreated = Object.values(created).reduce((sum, value) => sum + Number(value || 0), 0);

                this.createNotificationSuccess({
                    title: 'DemoDaten',
                    message: `Erfolgreich generiert (${totalCreated} Datensätze).`,
                });
            } catch (error) {
                const message = error?.response?.data?.message || error?.message || 'DemoDaten konnten nicht verarbeitet werden.';
                this.createNotificationError({
                    title: 'DemoDaten',
                    message,
                });
            } finally {
                this.isSeedingDemoData = false;
            }
        },

        async onSalesChannelChange() {
            await this.loadPdmsLieferzeiten();
        },

        async loadPdmsLieferzeiten() {
            if (!this.canLoadPdmsLieferzeiten) {
                this.pdmsPayload = null;
                return;
            }

            this.isLoading = true;

            try {
                this.pdmsPayload = await this.lieferzeitenOrdersService.getSalesChannelLieferzeiten(this.selectedSalesChannelId);
            } catch (error) {
                this.pdmsPayload = null;
                this.notifyRequestError(error, 'PDMS Lieferzeiten');
            } finally {
                this.isLoading = false;
            }
        },
    },
});
