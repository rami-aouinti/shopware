import template from './lieferzeiten-channel-settings-list.html.twig';
import { DOMAIN_GROUPS, normalizeDomainKey } from '../../../lieferzeiten/utils/domain-source-mapping';
import {
    isLmsTargetChannel,
    LMS_TARGET_CHANNELS,
    resolveLmsTargetChannelKey,
} from '../../../lieferzeiten/utils/lms-target-channel-mapping';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const DEFAULT_PDMS_LIEFERZEITEN = [
    { key: 'PDMS_1', label: 'PDMS 1', isPlaceholder: true },
    { key: 'PDMS_2', label: 'PDMS 2', isPlaceholder: true },
    { key: 'PDMS_3', label: 'PDMS 3', isPlaceholder: true },
    { key: 'PDMS_4', label: 'PDMS 4', isPlaceholder: true },
];

const LMS_TARGET_DOMAIN_KEYS = LMS_TARGET_CHANNELS.map((targetChannel) => targetChannel.domainKey);

const CHANNEL_GROUP_TITLES = {
    'first-medical-e-commerce': 'First Medical - E-Commerce',
    'medical-solutions': 'Medical Solutions',
};

const CHANNEL_GROUPS = Object.entries(CHANNEL_GROUP_TITLES)
    .map(([groupId, groupTitle]) => {
        const sourceGroup = DOMAIN_GROUPS.find((group) => group.id === groupId);

        return {
            id: groupId,
            title: groupTitle,
            domainKeys: (sourceGroup?.channels || [])
                .map((channel) => channel.value)
                .filter((channelDomainKey) => LMS_TARGET_DOMAIN_KEYS.includes(channelDomainKey)),
        };
    });

const OTHER_CHANNELS_GROUP = {
    id: 'other-channels',
    title: 'Weitere Kanäle',
};

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
            channelPdmsMappingIncomplete: {},
        };
    },

    computed: {
        groupedChannels() {
            const groupedEntries = CHANNEL_GROUPS.map((group) => ({
                ...group,
                channels: [],
            }));

            const channelsWithoutMapping = [];

            this.channels.forEach((channel) => {
                const domainKey = this.resolveChannelDomainKey(channel);
                const matchingGroup = groupedEntries.find((group) => group.domainKeys.includes(domainKey));

                if (matchingGroup) {
                    matchingGroup.channels.push(channel);
                    return;
                }

                channelsWithoutMapping.push(channel);
            });

            const visibleGroups = groupedEntries.filter((group) => group.channels.length > 0);

            if (channelsWithoutMapping.length > 0) {
                visibleGroups.push({
                    ...OTHER_CHANNELS_GROUP,
                    channels: channelsWithoutMapping,
                });
            }

            return visibleGroups;
        },

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
        resolveChannelDomainKey(channel) {
            const lmsTargetDomainKey = resolveLmsTargetChannelKey(channel);

            if (lmsTargetDomainKey) {
                return lmsTargetDomainKey;
            }

            const domainFromChannelDomain = channel?.domains
                ?.map((domain) => this.normalizeDomainFromUrl(domain?.url))
                .find((domainKey) => domainKey !== null);

            if (domainFromChannelDomain) {
                return domainFromChannelDomain;
            }

            return normalizeDomainKey(channel?.name);
        },

        normalizeDomainFromUrl(url) {
            if (!url) {
                return null;
            }

            try {
                const parsedUrl = new URL(url);
                return normalizeDomainKey(parsedUrl.hostname);
            } catch (error) {
                return normalizeDomainKey(url);
            }
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

        getChannelPdmsLieferzeiten(channelId) {
            return this.channelPdmsLieferzeiten[channelId] || DEFAULT_PDMS_LIEFERZEITEN;
        },

        getEntryKey(channelId, pdmsKey) {
            return `${channelId}.${pdmsKey}`;
        },

        hasIncompletePdmsMapping(channelId) {
            return Boolean(this.channelPdmsMappingIncomplete[channelId]);
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
                const entriesBySlot = new Map();

                if (Array.isArray(response?.lieferzeiten)) {
                    response.lieferzeiten.forEach((entry) => {
                        const slot = Number.parseInt(entry?.slot, 10);

                        if (!Number.isInteger(slot) || slot < 1 || slot > 4 || entriesBySlot.has(slot)) {
                            return;
                        }

                        entriesBySlot.set(slot, {
                            key: `PDMS_${slot}`,
                            label: entry?.lieferzeit?.name || `PDMS ${slot}`,
                            isPlaceholder: false,
                        });
                    });
                }

                const normalizedEntries = [1, 2, 3, 4].map((slot) => entriesBySlot.get(slot) || {
                    key: `PDMS_${slot}`,
                    label: this.$tc('lieferzeiten.lms.dashboard.incompleteMappingSlotLabel', 0, { slot }),
                    isPlaceholder: true,
                });

                this.$set(this.channelPdmsLieferzeiten, channelId, normalizedEntries);
                this.$set(this.channelPdmsMappingIncomplete, channelId, normalizedEntries.some((entry) => entry.isPlaceholder));
            } catch (error) {
                this.$set(this.channelPdmsLieferzeiten, channelId, DEFAULT_PDMS_LIEFERZEITEN);
                this.$set(this.channelPdmsMappingIncomplete, channelId, true);
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
            this.ensureChannelMatrix(channelId);
            this.matrixValues[channelId][pdmsKey][fieldName] = this.normalizeNonNegativeInteger(rawValue);
            this.validateValue(channelId, pdmsKey, fieldName);
        },

        getMatrixValue(channelId, pdmsKey, fieldName) {
            return this.matrixValues[channelId]?.[pdmsKey]?.[fieldName] ?? 0;
        },

        getFieldError(channelId, pdmsKey, fieldName) {
            return this.validationErrors[this.getFieldKey(channelId, pdmsKey, fieldName)] || null;
        },

        async loadData() {
            this.isLoading = true;

            const thresholdCriteria = new Criteria(1, 500);
            const salesChannelCriteria = new Criteria(1, 500);
            salesChannelCriteria.addSorting(Criteria.sort('name', 'ASC'));
            salesChannelCriteria.addAssociation('domains');

            try {
                const [thresholds, salesChannels] = await Promise.all([
                    this.thresholdRepository.search(thresholdCriteria, Shopware.Context.api),
                    this.salesChannelRepository.search(salesChannelCriteria, Shopware.Context.api),
                ]);

                this.channels = salesChannels.filter((channel) => isLmsTargetChannel(channel));
                this.matrixValues = {};
                this.existingEntryIds = {};
                this.channelPdmsLieferzeiten = {};
                this.channelPdmsMappingIncomplete = {};

                const loadedChannelIds = new Set(this.channels.map((channel) => channel.id));

                await Promise.all(this.channels.map((channel) => this.loadChannelPdmsLieferzeiten(channel.id)));

                this.channels.forEach((channel) => {
                    this.ensureChannelMatrix(channel.id);
                });

                thresholds.forEach((entry) => {
                    if (!loadedChannelIds.has(entry.salesChannelId)) {
                        return;
                    }

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
                    this.ensureChannelMatrix(channel.id);

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
