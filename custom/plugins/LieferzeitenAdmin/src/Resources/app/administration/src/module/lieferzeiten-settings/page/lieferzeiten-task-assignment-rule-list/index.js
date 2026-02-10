import template from './lieferzeiten-task-assignment-rule-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('lieferzeiten-task-assignment-rule-list', {
    template,

    inject: ['repositoryFactory'],

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
        columns() {
            return [
                { property: 'name', label: 'Name', inlineEdit: 'string', primary: true },
                { property: 'status', label: 'Status', inlineEdit: 'string' },
                { property: 'triggerKey', label: 'Trigger', inlineEdit: 'string' },
                { property: 'ruleId', label: 'Rule (Rules Engine)', inlineEdit: 'string' },
                { property: 'assigneeType', label: 'Assignee type', inlineEdit: 'string' },
                { property: 'assigneeIdentifier', label: 'Assignee', inlineEdit: 'string' },
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
            this.isLoading = true;
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
            entity.name = 'New rule';
            entity.triggerKey = this.triggerOptions[0];
            entity.assigneeType = this.assigneeTypeOptions[0];
            entity.active = false;
            return this.repository.save(entity, Shopware.Context.api).then(() => {
                this.getList();
            });
        },
    },
});
