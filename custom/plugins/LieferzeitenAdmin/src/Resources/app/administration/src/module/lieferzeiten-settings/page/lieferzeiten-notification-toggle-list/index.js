import template from './lieferzeiten-notification-toggle-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-notification-toggle-list', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            repository: null,
            items: null,
            isLoading: false,
            total: 0,
            page: 1,
            limit: 50,
            triggerOptions: [
                'commande.creee',
                'commande.changement_statut',
                'tracking.mis_a_jour',
                'changements.date_livraison',
                'douane.requise',
                'rappel.vorkasse',
            ],
            channelOptions: ['email', 'sms', 'webhook'],
        };
    },

    computed: {
        columns() {
            return [
                { property: 'triggerKey', label: 'Trigger', inlineEdit: 'string', primary: true },
                { property: 'channel', label: 'Canal', inlineEdit: 'string' },
                { property: 'salesChannelId', label: 'Sales Channel Scope', inlineEdit: 'string' },
                { property: 'enabled', label: 'Enabled', inlineEdit: 'boolean' },
                { property: 'lastChangedBy', label: 'Last Changed By', inlineEdit: 'string' },
                { property: 'lastChangedAt', label: 'Last Changed At', inlineEdit: 'date' },
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_notification_toggle');
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('triggerKey', 'ASC'));
            criteria.addSorting(Criteria.sort('channel', 'ASC'));

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
            this.isLoading = true;
            item.code = `${item.triggerKey}:${item.channel}`;
            return this.repository.save(item, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
        onDelete(item) {
            this.isLoading = true;
            return this.repository.delete(item.id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
        onCreate() {
            const entity = this.repository.create(Shopware.Context.api);
            entity.triggerKey = 'commande.creee';
            entity.channel = 'email';
            entity.salesChannelId = null;
            entity.code = `${entity.triggerKey}:${entity.channel}`;
            entity.enabled = true;
            return this.repository.save(entity, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
    },
});
