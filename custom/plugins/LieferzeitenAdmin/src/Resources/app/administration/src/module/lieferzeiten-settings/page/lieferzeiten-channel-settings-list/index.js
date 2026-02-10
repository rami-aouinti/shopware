import template from './lieferzeiten-channel-settings-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-channel-settings-list', {
    template,

    mixins: ['notification'],

    inject: ['repositoryFactory', 'lieferzeitenOrdersService'],

    data() {
        return {
            repository: null,
            items: null,
            isLoading: false,
            total: 0,
            page: 1,
            limit: 25,
            isSeedingDemoData: false,
        };
    },

    computed: {
        hasEditAccess() {
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
    },

    methods: {
        async onSeedDemoData(reset = false) {
            if (!this.hasEditAccess) {
                return;
            }

            this.isSeedingDemoData = true;

            try {
                const response = await this.lieferzeitenOrdersService.seedDemoData(reset);
                const created = response?.created || {};
                const totalCreated = Object.values(created).reduce((sum, value) => sum + Number(value || 0), 0);

                this.createNotificationSuccess({
                    title: 'DemoDaten',
                    message: `Erfolgreich generiert (${totalCreated} Datensätze).`,
                });
            } catch (error) {
                const message = error?.response?.data?.message || error?.message || 'DemoDaten konnten nicht erzeugt werden.';
                this.createNotificationError({
                    title: 'DemoDaten',
                    message,
                });
            } finally {
                this.isSeedingDemoData = false;
            }
        },

        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

            return this.repository.search(criteria, Shopware.Context.api).then((result) => {
                this.items = result;
                this.total = result.total;
            }).finally(() => {
                this.isLoading = false;
            });
        },
        onPageChange({ page, limit }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },
        onInlineEditSave(item) {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            this.isLoading = true;
            return this.repository.save(item, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
        onDelete(item) {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            this.isLoading = true;
            return this.repository.delete(item.id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
        onCreate() {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            const entity = this.repository.create(Shopware.Context.api);
            entity.salesChannelId = '';
            entity.enableNotifications = false;
            return this.repository.save(entity, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
    },
});
