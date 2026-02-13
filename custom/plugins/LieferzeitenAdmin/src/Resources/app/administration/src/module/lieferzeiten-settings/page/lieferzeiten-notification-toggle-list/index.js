import template from './lieferzeiten-notification-toggle-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-notification-toggle-list', {
    template,

    mixins: ['notification'],

    inject: ['repositoryFactory', 'acl'],

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
                'versand.datum.ueberfaellig',
                'liefertermin.anfrage.zusaetzlich',
                'liefertermin.anfrage.geschlossen',
            ],
            channelOptions: ['email', 'sms', 'webhook'],
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

        async getList() {
            this.isLoading = true;
            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort('triggerKey', 'ASC'));
            criteria.addSorting(Criteria.sort('channel', 'ASC'));

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
            item.code = `${item.triggerKey}:${item.channel}`;
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
            entity.triggerKey = 'commande.creee';
            entity.channel = 'email';
            entity.salesChannelId = null;
            entity.code = `${entity.triggerKey}:${entity.channel}`;
            entity.enabled = true;
            try {
                await this.repository.save(entity, Shopware.Context.api);
                await this.getList();
            } catch (error) {
                this.notifyRequestError(error, 'Lieferzeiten Settings');
            }
        },
    },
});
