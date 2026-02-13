import template from './lieferzeiten-channel-settings-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-channel-settings-list', {
    template,

    mixins: ['notification'],

    inject: ['repositoryFactory', 'lieferzeitenOrdersService', 'acl'],

    data() {
        return {
            repository: null,
            items: null,
            isLoading: false,
            total: 0,
            page: 1,
            limit: 25,
            isSeedingDemoData: false,
            hasDemoData: false,
        };
    },

    computed: {
        hasEditAccess() {
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.editor') || this.acl.can('admin');
        },

        columns() {
            return [
                { property: 'salesChannelId', label: 'Sales Channel', inlineEdit: 'string', primary: true },
                { property: 'defaultStatus', label: 'Default Status', inlineEdit: 'string' },
                { property: 'enableNotifications', label: 'Notifications', inlineEdit: 'boolean' },
                { property: 'lastChangedBy', label: 'Last Changed By', inlineEdit: 'string' },
                { property: 'lastChangedAt', label: 'Last Changed At', inlineEdit: 'date' },
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_channel_settings');
        this.getList();
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

        async getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            try {
                const result = await this.repository.search(criteria, Shopware.Context.api);
                this.items = result;
                this.total = result.total;
            } catch (error) {
                this.notifyRequestError(error, 'Lieferzeiten Settings');
            } finally {
                this.isLoading = false;
            }
        },
        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },
        async onInlineEditSave(item) {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            this.isLoading = true;
            try {
                await this.repository.save(item, Shopware.Context.api);
                await this.getList();
            } catch (error) {
                this.notifyRequestError(error, 'Lieferzeiten Settings');
            }
        },
        async onDelete(item) {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            this.isLoading = true;
            try {
                await this.repository.delete(item.id, Shopware.Context.api);
                await this.getList();
            } catch (error) {
                this.notifyRequestError(error, 'Lieferzeiten Settings');
            }
        },
        async onCreate() {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            const entity = this.repository.create(Shopware.Context.api);
            entity.salesChannelId = '';
            entity.enableNotifications = false;
            try {
                await this.repository.save(entity, Shopware.Context.api);
                await this.getList();
            } catch (error) {
                this.notifyRequestError(error, 'Lieferzeiten Settings');
            }
        },
    },
});
