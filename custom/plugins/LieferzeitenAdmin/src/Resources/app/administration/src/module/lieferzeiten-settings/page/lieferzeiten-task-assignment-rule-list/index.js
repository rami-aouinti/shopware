import template from './lieferzeiten-task-assignment-rule-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-task-assignment-rule-list', {
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
            limit: 25,
            triggerOptions: [
                'versand.datum.ueberfaellig',
                'liefertermin.anfrage.zusaetzlich',
                'liefertermin.anfrage.geschlossen',
            ],
            assigneeTypeOptions: ['user', 'team'],
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
                { property: 'name', label: 'Name', inlineEdit: 'string', primary: true },
                { property: 'status', label: 'Status (optional)', inlineEdit: 'string' },
                { property: 'triggerKey', label: 'Trigger *', inlineEdit: 'string' },
                { property: 'ruleId', label: 'Rule (Rules Engine, optional)', inlineEdit: 'string' },
                { property: 'assigneeType', label: 'Assignee type *', inlineEdit: 'string' },
                { property: 'assigneeIdentifier', label: 'Assignee *', inlineEdit: 'string' },
                { property: 'priority', label: 'Priority', inlineEdit: 'number' },
                { property: 'active', label: 'Active', inlineEdit: 'boolean' },
                { property: 'lastChangedBy', label: 'Last Changed By', inlineEdit: 'string' },
                { property: 'lastChangedAt', label: 'Last Changed At', inlineEdit: 'date' },
            ];
        },
    },

    created() {
        this.repository = this.repositoryFactory.create('lieferzeiten_task_assignment_rule');
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

        validateRule(item) {
            const triggerKey = String(item?.triggerKey ?? '').trim();
            const assigneeType = String(item?.assigneeType ?? '').trim();
            const assigneeIdentifier = String(item?.assigneeIdentifier ?? '').trim();
            const ruleId = String(item?.ruleId ?? '').trim();
            const rawConditions = item?.conditions;

            const hasConditions = (typeof rawConditions === 'string' && rawConditions.trim() !== '')
                || (rawConditions && typeof rawConditions === 'object' && Object.keys(rawConditions).length > 0);

            if (!triggerKey || !assigneeType || !assigneeIdentifier) {
                return 'Die Pflichtfelder Trigger, Assignee type und Assignee müssen gesetzt sein.';
            }

            if (item?.active && !ruleId && !hasConditions) {
                return 'Aktive Regeln benötigen mindestens Rule (Rules Engine) oder Conditions JSON, sonst sind sie nicht operant.';
            }

            return null;
        },

        notifyValidationError(message) {
            this.createNotificationError({
                title: this.$tc('lieferzeiten.lms.navigation.taskAssignmentRules'),
                message,
            });
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
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.navigation.taskAssignmentRules'));
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

            const validationError = this.validateRule(item);
            if (validationError) {
                this.notifyValidationError(validationError);
                return Promise.resolve();
            }

            this.isLoading = true;
            try {
                await this.repository.save(item, Shopware.Context.api);
                await this.getList();
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.navigation.taskAssignmentRules'));
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
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.navigation.taskAssignmentRules'));
            }
        },
        async onCreate() {
            if (!this.hasEditAccess) {
                return Promise.resolve();
            }

            const entity = this.repository.create(Shopware.Context.api);
            entity.name = 'New rule';
            entity.triggerKey = this.triggerOptions[0];
            entity.assigneeType = this.assigneeTypeOptions[0];
            entity.assigneeIdentifier = '';
            entity.active = false;
            try {
                await this.repository.save(entity, Shopware.Context.api);
                await this.getList();
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.navigation.taskAssignmentRules'));
            }
        },
    },
});
