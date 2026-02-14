import template from './lieferzeiten-channel-settings-list.html.twig';
import { DOMAIN_GROUPS, normalizeDomainKey } from '../../../lieferzeiten/utils/domain-source-mapping';
import {
    isLmsTargetChannel,
    LMS_TARGET_CHANNELS,
    resolveLmsTargetChannelKey,
} from '../../../lieferzeiten/utils/lms-target-channel-mapping';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

const PDMS_SLOTS = [1, 2, 3, 4];

/**
 * LMS whitelist matching is resolved in lms-target-channel-mapping via:
 * 1) canonical sales-channel domain mapping (normalized hostname)
 * 2) explicit technical identifiers (exact match after normalization)
 *
 * Display names are not used as fuzzy matching input.
 */
const LMS_TARGET_DOMAIN_KEYS = LMS_TARGET_CHANNELS.map((targetChannel) => targetChannel.domainKey);

const CHANNEL_GROUP_TITLES = {
    'first-medical-e-commerce': 'First Medical - E-Commerce',
    'medical-solutions': 'Medical Solutions',
};

const LMS_FALLBACK_CHANNEL_IDS = Object.freeze({
    'first-medical-shop.de': '11111111-1111-4111-8111-111111111111',
    'ebay.de': '22222222-2222-4222-8222-222222222222',
    'ebay.at': '33333333-3333-4333-8333-333333333333',
    kaufland: '44444444-4444-4444-8444-444444444444',
    peg: '55555555-5555-4555-8555-555555555555',
    zonami: '66666666-6666-4666-8666-666666666666',
    'medical-solutions-germany.de': '77777777-7777-4777-8777-777777777777',
});

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
        createSyntheticLmsChannel(domainKey) {
            return {
                id: LMS_FALLBACK_CHANNEL_IDS[domainKey] || Shopware.Utils.createId(),
                name: domainKey,
                technicalName: domainKey,
                domains: [{ url: `https://${domainKey}` }],
                _isSyntheticLmsChannel: true,
            };
        },

        appendMissingLmsTargetChannels(channels) {
            const resolvedDomainKeys = new Set(channels
                .map((channel) => this.resolveChannelDomainKey(channel))
                .filter((domainKey) => domainKey !== null));

            const mergedChannels = [...channels];

            LMS_TARGET_DOMAIN_KEYS.forEach((domainKey) => {
                if (resolvedDomainKeys.has(domainKey)) {
                    return;
                }

                mergedChannels.push(this.createSyntheticLmsChannel(domainKey));
            });

            return mergedChannels;
        },

        getWhitelistedChannels(salesChannels) {
            const whitelistedChannels = salesChannels.filter((channel) => isLmsTargetChannel(channel));

            return this.appendMissingLmsTargetChannels(whitelistedChannels);
        },

        getWhitelistedChannelIds() {
            return new Set(this.channels.map((channel) => channel.id));
        },

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
            return this.channelPdmsLieferzeiten[channelId] || this.buildNormalizedPdmsSlots();
        },

        createMissingSlotEntry(slot) {
            return {
                key: `PDMS_${slot}`,
                label: this.$tc('lieferzeiten.lms.dashboard.incompleteMappingSlotLabel', 0, { slot }),
                isPlaceholder: true,
            };
        },

        buildNormalizedPdmsSlots(entriesBySlot = new Map()) {
            return PDMS_SLOTS.map((slot) => entriesBySlot.get(slot) || this.createMissingSlotEntry(slot));
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
                this.matrixValues[channelId] = {};
            }

            this.getChannelPdmsLieferzeiten(channelId).forEach(({ key }) => {
                if (!this.matrixValues[channelId][key]) {
                    this.matrixValues[channelId][key] = {
                        shippingOverdueWorkingDays: 0,
                        deliveryOverdueWorkingDays: 0,
                    };
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

                const normalizedEntries = this.buildNormalizedPdmsSlots(entriesBySlot);

                this.channelPdmsLieferzeiten[channelId] = normalizedEntries;
                this.channelPdmsMappingIncomplete[channelId] = normalizedEntries.some((entry) => entry.isPlaceholder);
            } catch (error) {
                this.channelPdmsLieferzeiten[channelId] = this.buildNormalizedPdmsSlots();
                this.channelPdmsMappingIncomplete[channelId] = true;
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
                this.validationErrors[fieldKey] = this.$tc('lieferzeiten.lms.dashboard.inlineValidationError');
                return;
            }

            delete this.validationErrors[fieldKey];
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

                this.channels = this.getWhitelistedChannels(salesChannels);
                this.matrixValues = {};
                this.existingEntryIds = {};
                this.channelPdmsLieferzeiten = {};
                this.channelPdmsMappingIncomplete = {};

                const loadedChannelIds = this.getWhitelistedChannelIds();

                await Promise.all(this.channels.map((channel) => this.loadChannelPdmsLieferzeiten(channel.id)));

                this.channels.forEach((channel) => {
                    this.ensureChannelMatrix(channel.id);
                });

                thresholds.forEach((entry) => {
                    if (!loadedChannelIds.has(entry.salesChannelId)) {
                        return;
                    }

                    this.ensureChannelMatrix(entry.salesChannelId);
                    this.matrixValues[entry.salesChannelId][entry.pdmsLieferzeit] = {
                        shippingOverdueWorkingDays: entry.shippingOverdueWorkingDays,
                        deliveryOverdueWorkingDays: entry.deliveryOverdueWorkingDays,
                    };
                    this.existingEntryIds[this.getEntryKey(entry.salesChannelId, entry.pdmsLieferzeit)] = entry.id;
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
                const whitelistedChannelIds = this.getWhitelistedChannelIds();

                for (const channel of this.channels) {
                    if (!whitelistedChannelIds.has(channel.id)) {
                        continue;
                    }

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
