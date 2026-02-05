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
            taskAssignmentRepository: null,
            items: [],
            taskAssignments: [],
            isLoading: false,
            isAssignmentLoading: false,
            areaOptions: [
                { value: 'first-medical', label: this.$t('lieferzeiten-management.general.areaFirstMedical') },
                { value: 'e-commerce', label: this.$t('lieferzeiten-management.general.areaECommerce') },
                { value: 'medical-solutions', label: this.$t('lieferzeiten-management.general.areaMedicalSolutions') },
            ],
            taskTypeOptions: [
                { value: 'shipping_overdue', label: this.$t('lieferzeiten-management.settings.taskTypeShippingOverdue') },
                { value: 'additional_delivery_request', label: this.$t('lieferzeiten-management.settings.taskTypeAdditionalDelivery') },
            ],
        };
    },

    created() {
        this.settingsRepository = this.repositoryFactory.create('lieferzeiten_settings');
        this.taskAssignmentRepository = this.repositoryFactory.create('lieferzeiten_task_assignment');
        this.loadSettings();
        this.loadTaskAssignments();
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

        loadTaskAssignments() {
            this.isAssignmentLoading = true;
            const criteria = new Criteria(1, 250);
            criteria.addAssociation('salesChannel');
            criteria.addAssociation('assignedUser');

            this.taskAssignmentRepository.search(criteria, Shopware.Context.api).then((result) => {
                this.taskAssignments = result;
            }).finally(() => {
                this.isAssignmentLoading = false;
            });
        },

        addMapping() {
            const setting = this.settingsRepository.create(Shopware.Context.api);
            this.items.unshift(setting);
        },

        addTaskAssignment() {
            const assignment = this.taskAssignmentRepository.create(Shopware.Context.api);
            this.taskAssignments.unshift(assignment);
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

        saveTaskAssignment(item) {
            this.taskAssignmentRepository.save(item, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.settings.taskAssignmentSaveSuccess'),
                });
                this.loadTaskAssignments();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.settings.taskAssignmentSaveError'),
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

        deleteTaskAssignment(item) {
            if (!item?.id) {
                return;
            }

            this.taskAssignmentRepository.delete(item.id, Shopware.Context.api).then(() => {
                this.createNotificationSuccess({
                    title: this.$t('global.default.success'),
                    message: this.$t('lieferzeiten-management.settings.taskAssignmentDeleteSuccess'),
                });
                this.loadTaskAssignments();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$t('global.default.error'),
                    message: this.$t('lieferzeiten-management.settings.taskAssignmentDeleteError'),
                });
            });
        },
    },
});
