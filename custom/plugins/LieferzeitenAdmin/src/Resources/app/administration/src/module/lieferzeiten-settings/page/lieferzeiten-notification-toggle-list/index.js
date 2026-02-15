import template from './lieferzeiten-notification-toggle-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-notification-toggle-list', {
    template,

    mixins: ['notification'],

    inject: ['repositoryFactory'],

    data() {
        return {
            repository: null,
            salesChannelRepository: null,
            items: [],
            salesChannels: [],
            isLoading: false,
            triggerOptions: [
                'commande.creee',
                'commande.changement_statut',
                'tracking.mis_a_jour',
                'expedition.confirmee',
                'changements.date_livraison',
                'livraison.date.attribuee',
                'livraison.date.modifiee',
                'douane.requise',
                'commande.storno',
                'livraison.impossible',
                'livraison.retoure',
                'rappel.vorkasse',
                'paiement.recu.vorkasse',
                'commande.terminee.rappel_evaluation',
                'versand.datum.ueberfaellig',
                'liefertermin.anfrage.zusaetzlich',
                'liefertermin.anfrage.geschlossen',
                'liefertermin.anfrage.wiedereroeffnet',
            ],
            channelOptions: ['email'],
            invalidEntries: [],
        };
    },

    computed: {
        hasEditAccess() {
            const aclService = this.getAclService();

            if (!aclService) {
                return false;
            }

            return aclService.can('lieferzeiten.editor') || aclService.can('admin');
        },

        salesChannelColumns() {
            return [
                {
                    id: 'global',
                    name: 'Global',
                },
                ...this.salesChannels.map((salesChannel) => ({
                    id: salesChannel.id,
                    name: salesChannel.name || salesChannel.id,
                })),
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_notification_toggle');
        this.salesChannelRepository = this.repositoryFactory.create('sales_channel');
        this.loadData();
    },

    methods: {
        getAclService() {
            const injectedAcl = this.acl;

            if (typeof injectedAcl?.can === 'function') {
                return injectedAcl;
            }

            const serviceAcl = Shopware.Application.getContainer('service')?.acl;

            if (typeof serviceAcl?.can === 'function') {
                return serviceAcl;
            }

            return null;
        },

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

        async loadData() {
            this.isLoading = true;

            try {
                const toggleCriteria = new Criteria(1, 500);
                toggleCriteria.addSorting(Criteria.sort('triggerKey', 'ASC'));
                toggleCriteria.addSorting(Criteria.sort('channel', 'ASC'));
                toggleCriteria.addSorting(Criteria.sort('salesChannelId', 'ASC'));

                const salesChannelCriteria = new Criteria(1, 500);
                salesChannelCriteria.addSorting(Criteria.sort('name', 'ASC'));

                const [toggleResult, salesChannelResult] = await Promise.all([
                    this.repository.search(toggleCriteria, Shopware.Context.api),
                    this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api),
                ]);

                this.items = [...toggleResult];
                this.salesChannels = [...salesChannelResult];
                this.invalidEntries = this.items.filter((item) => !this.isValidToggle(item));
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.general.mainMenuItem'));
            } finally {
                this.isLoading = false;
            }
        },

        getToggleEntity(triggerKey, channel, salesChannelId) {
            const scopeId = salesChannelId || null;

            return this.items.find((item) => item.triggerKey === triggerKey
                && item.channel === channel
                && (item.salesChannelId || null) === scopeId);
        },

        getToggleValue(triggerKey, channel, salesChannelId) {
            return Boolean(this.getToggleEntity(triggerKey, channel, salesChannelId)?.enabled);
        },

        isValidToggle(item) {
            const expectedCode = `${item.triggerKey}:${item.channel}`;

            return this.triggerOptions.includes(item.triggerKey)
                && this.channelOptions.includes(item.channel)
                && item.code === expectedCode;
        },

        isCellDisabled(triggerKey, channel, salesChannelId) {
            const existingEntity = this.getToggleEntity(triggerKey, channel, salesChannelId);

            if (!existingEntity) {
                return false;
            }

            return !this.isValidToggle(existingEntity);
        },

        async onToggleChanged(triggerKey, channel, salesChannelId, enabled) {
            if (!this.hasEditAccess) {
                return;
            }

            if (!this.triggerOptions.includes(triggerKey)) {
                this.createNotificationError({
                    title: 'Validation',
                    message: `Trigger invalide: ${triggerKey}`,
                });

                return;
            }

            if (!this.channelOptions.includes(channel)) {
                this.createNotificationError({
                    title: 'Validation',
                    message: `Canal invalide: ${channel}`,
                });

                return;
            }

            const normalizedSalesChannelId = salesChannelId || null;
            let entity = this.getToggleEntity(triggerKey, channel, normalizedSalesChannelId);

            if (entity && !this.isValidToggle(entity)) {
                this.createNotificationError({
                    title: 'Validation',
                    message: 'Impossible de modifier une entrée incohérente. Corrigez triggerKey/channel/code via API ou supprimez-la.',
                });

                return;
            }

            if (!entity) {
                entity = this.repository.create(Shopware.Context.api);
                entity.triggerKey = triggerKey;
                entity.channel = channel;
                entity.salesChannelId = normalizedSalesChannelId;
            }

            entity.code = `${triggerKey}:${channel}`;
            entity.enabled = Boolean(enabled);

            this.isLoading = true;

            try {
                await this.repository.save(entity, Shopware.Context.api);
                await this.loadData();
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.general.mainMenuItem'));
            } finally {
                this.isLoading = false;
            }
        },
    },
});
