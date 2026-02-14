import template from './lieferzeiten-channel-settings-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const DEFAULT_PDMS_LIEFERZEITEN = [
    { key: 'PDMS_1', label: 'PDMS 1' },
    { key: 'PDMS_2', label: 'PDMS 2' },
    { key: 'PDMS_3', label: 'PDMS 3' },
    { key: 'PDMS_4', label: 'PDMS 4' },
];

Component.register('lieferzeiten-channel-settings-list', {
    template,

    mixins: ['notification'],

    inject: ['repositoryFactory', 'lieferzeitenOrdersService', 'acl'],

    data() {
        return {
            thresholdRepository: null,
            salesChannelRepository: null,
            channels: [],
            matrixValues: {},
            existingEntryIds: {},
            validationErrors: {},
            isLoading: false,
            isSaving: false,
            isSeedingDemoData: false,
            hasDemoData: false,
            channelPdmsLieferzeiten: {},
        };
    },

    computed: {
        hasEditAccess() {
            if (typeof this.acl?.can !== 'function') {
                return false;
            }

            return this.acl.can('lieferzeiten.editor') || this.acl.can('admin');
        },

        hasValidationErrors() {
            return Object.keys(this.validationErrors).length > 0;
        },
    },

    created() {
        this.thresholdRepository = this.repositoryFactory.create('lieferzeiten_channel_pdms_threshold');
        this.salesChannelRepository = this.repositoryFactory.create('sales_channel');
        this.loadData();
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

        getChannelPdmsLieferzeiten(channelId) {
            return this.channelPdmsLieferzeiten[channelId] || DEFAULT_PDMS_LIEFERZEITEN;
        },

        getEntryKey(channelId, pdmsKey) {
            return `${channelId}.${pdmsKey}`;
        },

        getFieldKey(channelId, pdmsKey, fieldName) {
            return `${channelId}.${pdmsKey}.${fieldName}`;
        },

        ensureChannelMatrix(channelId) {
            if (!this.matrixValues[channelId]) {
                this.$set(this.matrixValues, channelId, {});
            }

            this.getChannelPdmsLieferzeiten(channelId).forEach(({ key }) => {
                if (!this.matrixValues[channelId][key]) {
                    this.$set(this.matrixValues[channelId], key, {
                        shippingOverdueWorkingDays: 0,
                        deliveryOverdueWorkingDays: 0,
                    });
                }
            });
        },

        async loadChannelPdmsLieferzeiten(channelId) {
            try {
                const response = await this.lieferzeitenOrdersService.getSalesChannelLieferzeiten(channelId);
                const entries = Array.isArray(response?.lieferzeiten)
                    ? response.lieferzeiten.map((entry) => ({
                        key: `PDMS_${entry?.slot ?? 0}`,
                        label: entry?.lieferzeit?.name || `PDMS ${entry?.slot ?? ''}`,
                    })).filter((entry) => /^PDMS_[1-4]$/.test(entry.key))
                    : [];

                const normalizedEntries = entries.length === 4
                    ? entries
                    : DEFAULT_PDMS_LIEFERZEITEN;

                this.$set(this.channelPdmsLieferzeiten, channelId, normalizedEntries);
            } catch (error) {
                this.$set(this.channelPdmsLieferzeiten, channelId, DEFAULT_PDMS_LIEFERZEITEN);
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.dashboard.title'));
            }
        },

        normalizeNonNegativeInteger(rawValue) {
            if (typeof rawValue === 'number') {
                return Number.isInteger(rawValue) ? rawValue : rawValue;
            }

            if (typeof rawValue !== 'string') {
                return rawValue;
            }

            const trimmed = rawValue.trim();
            if (!/^\d+$/.test(trimmed)) {
                return rawValue;
            }

            return Number.parseInt(trimmed, 10);
        },

        validateValue(channelId, pdmsKey, fieldName) {
            const fieldKey = this.getFieldKey(channelId, pdmsKey, fieldName);
            const value = this.matrixValues[channelId]?.[pdmsKey]?.[fieldName];
            const isValid = Number.isInteger(value) && value >= 0;

            if (!isValid) {
                this.$set(this.validationErrors, fieldKey, this.$tc('lieferzeiten.lms.dashboard.inlineValidationError'));
                return;
            }

            this.$delete(this.validationErrors, fieldKey);
        },

        onChangeNumberField(channelId, pdmsKey, fieldName, rawValue) {
            this.matrixValues[channelId][pdmsKey][fieldName] = this.normalizeNonNegativeInteger(rawValue);
            this.validateValue(channelId, pdmsKey, fieldName);
        },

        getFieldError(channelId, pdmsKey, fieldName) {
            return this.validationErrors[this.getFieldKey(channelId, pdmsKey, fieldName)] || null;
        },

        async loadData() {
            this.isLoading = true;

            const thresholdCriteria = new Criteria(1, 500);
            const salesChannelCriteria = new Criteria(1, 500);
            salesChannelCriteria.addSorting(Criteria.sort('name', 'ASC'));

            try {
                const [thresholds, salesChannels] = await Promise.all([
                    this.thresholdRepository.search(thresholdCriteria, Shopware.Context.api),
                    this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api),
                ]);

                this.channels = salesChannels;
                this.matrixValues = {};
                this.existingEntryIds = {};
                this.channelPdmsLieferzeiten = {};

                await Promise.all(this.channels.map((channel) => this.loadChannelPdmsLieferzeiten(channel.id)));

                this.channels.forEach((channel) => {
                    this.ensureChannelMatrix(channel.id);
                });

                thresholds.forEach((entry) => {
                    this.ensureChannelMatrix(entry.salesChannelId);
                    this.$set(this.matrixValues[entry.salesChannelId], entry.pdmsLieferzeit, {
                        shippingOverdueWorkingDays: entry.shippingOverdueWorkingDays,
                        deliveryOverdueWorkingDays: entry.deliveryOverdueWorkingDays,
                    });
                    this.$set(this.existingEntryIds, this.getEntryKey(entry.salesChannelId, entry.pdmsLieferzeit), entry.id);
                });
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.dashboard.title'));
            } finally {
                this.isLoading = false;
            }
        },

        async onSave() {
            if (!this.hasEditAccess) {
                return;
            }

            this.validationErrors = {};
            this.channels.forEach((channel) => {
                this.getChannelPdmsLieferzeiten(channel.id).forEach(({ key }) => {
                    this.validateValue(channel.id, key, 'shippingOverdueWorkingDays');
                    this.validateValue(channel.id, key, 'deliveryOverdueWorkingDays');
                });
            });

            if (this.hasValidationErrors) {
                this.createNotificationError({
                    title: this.$tc('lieferzeiten.lms.dashboard.title'),
                    message: this.$tc('lieferzeiten.lms.dashboard.validationError'),
                });

                return;
            }

            this.isSaving = true;

            try {
                for (const channel of this.channels) {
                    for (const { key } of this.getChannelPdmsLieferzeiten(channel.id)) {
                        const entity = this.thresholdRepository.create(Shopware.Context.api);
                        entity.id = this.existingEntryIds[this.getEntryKey(channel.id, key)] || entity.id;
                        entity.salesChannelId = channel.id;
                        entity.pdmsLieferzeit = key;
                        entity.shippingOverdueWorkingDays = this.matrixValues[channel.id][key].shippingOverdueWorkingDays;
                        entity.deliveryOverdueWorkingDays = this.matrixValues[channel.id][key].deliveryOverdueWorkingDays;

                        await this.thresholdRepository.save(entity, Shopware.Context.api);
                    }
                }

                this.createNotificationSuccess({
                    title: this.$tc('lieferzeiten.lms.dashboard.title'),
                    message: this.$tc('lieferzeiten.lms.dashboard.saveSuccess'),
                });

                await this.loadData();
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.dashboard.title'));
            } finally {
                this.isSaving = false;
            }
        },

        async loadDemoDataStatus() {
            try {
                const response = await this.lieferzeitenOrdersService.getDemoDataStatus();
                this.hasDemoData = Boolean(response?.hasDemoData);
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.dashboard.demoDataTitle'));
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
                await this.loadData();

                this.createNotificationSuccess({
                    title: this.$tc('lieferzeiten.lms.dashboard.demoDataTitle'),
                    message: response?.action === 'removed'
                        ? this.$tc('lieferzeiten.lms.dashboard.demoDataRemoved')
                        : this.$tc('lieferzeiten.lms.dashboard.demoDataSaved'),
                });
            } catch (error) {
                this.notifyRequestError(error, this.$tc('lieferzeiten.lms.dashboard.demoDataTitle'));
            } finally {
                this.isSeedingDemoData = false;
            }
        },
    },
});
