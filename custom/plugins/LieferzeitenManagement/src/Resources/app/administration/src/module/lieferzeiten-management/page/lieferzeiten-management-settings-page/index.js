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
            items: [],
            isLoading: false,
            areaOptions: [
                { value: 'first-medical', label: this.$t('lieferzeiten-management.general.areaFirstMedical') },
                { value: 'e-commerce', label: this.$t('lieferzeiten-management.general.areaECommerce') },
                { value: 'medical-solutions', label: this.$t('lieferzeiten-management.general.areaMedicalSolutions') },
            ],
        };
    },

    created() {
        this.settingsRepository = this.repositoryFactory.create('lieferzeiten_settings');
        this.loadSettings();
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

        addMapping() {
            const setting = this.settingsRepository.create(Shopware.Context.api);
            this.items.unshift(setting);
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
    },
});
