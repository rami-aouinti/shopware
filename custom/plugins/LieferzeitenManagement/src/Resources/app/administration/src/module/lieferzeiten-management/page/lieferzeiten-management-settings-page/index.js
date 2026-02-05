import template from './lieferzeiten-management-settings-page.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.register('lieferzeiten-management-settings-page', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    data() {
        return {
            settingsRepository: null,
            notificationSettingsRepository: null,
            items: [],
            notificationItems: [],
            isLoading: false,
            notificationIsLoading: false,
            areaOptions: [
                { value: 'first-medical', label: this.$t('lieferzeiten-management.general.areaFirstMedical') },
                { value: 'e-commerce', label: this.$t('lieferzeiten-management.general.areaECommerce') },
                { value: 'medical-solutions', label: this.$t('lieferzeiten-management.general.areaMedicalSolutions') },
            ],
            notificationOptions: [
                {
                    value: 'order_created',
                    label: this.$t('lieferzeiten-management.settings.notificationOrderCreated'),
                },
                {
                    value: 'tracking_available',
                    label: this.$t('lieferzeiten-management.settings.notificationTrackingAvailable'),
                },
                {
                    value: 'delivery_date_changed',
                    label: this.$t('lieferzeiten-management.settings.notificationDeliveryDateChanged'),
                },
            ],
        };
    },

    created() {
        this.settingsRepository = this.repositoryFactory.create('lieferzeiten_settings');
        this.notificationSettingsRepository = this.repositoryFactory.create('lieferzeiten_notification_settings');
        this.loadSettings();
        this.loadNotificationSettings();
    },

    methods: {
        loadSettings() {
            this.isLoading = true;
            const criteria = new Criteria(1, 250);
            criteria.addAssociation('salesChannel');

            this.settingsRepository.search(criteria, Shopware.Context.api).then((result) => {
                this.items = result;
            }).finally(() => {
                this.isLoading = false;
            });
        },
        loadNotificationSettings() {
            this.notificationIsLoading = true;
            const criteria = new Criteria(1, 250);
            criteria.addAssociation('salesChannel');

            this.notificationSettingsRepository.search(criteria, Shopware.Context.api).then((result) => {
                this.notificationItems = result;
            }).finally(() => {
                this.notificationIsLoading = false;
            });
        },

        addMapping() {
            const setting = this.settingsRepository.create(Shopware.Context.api);
            this.items.unshift(setting);
        },
        addNotificationSetting() {
            const setting = this.notificationSettingsRepository.create(Shopware.Context.api);
            setting.enabled = true;
            this.notificationItems.unshift(setting);
        },

        saveMapping(item) {
            this.settingsRepository.save(item, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.settings.saveSuccess'),
                });
                this.loadSettings();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.settings.saveError'),
                });
            });
        },
        saveNotificationSetting(item) {
            this.notificationSettingsRepository.save(item, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.settings.notificationSaveSuccess'),
                });
                this.loadNotificationSettings();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.settings.notificationSaveError'),
                });
            });
        },

        deleteMapping(item) {
            if (!item?.id) {
                return;
            }

            this.settingsRepository.delete(item.id, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.settings.deleteSuccess'),
                });
                this.loadSettings();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.settings.deleteError'),
                });
            });
        },
        deleteNotificationSetting(item) {
            if (!item?.id) {
                return;
            }

            this.notificationSettingsRepository.delete(item.id, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.settings.notificationDeleteSuccess'),
                });
                this.loadNotificationSettings();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.settings.notificationDeleteError'),
                });
            });
        },
    },
});
