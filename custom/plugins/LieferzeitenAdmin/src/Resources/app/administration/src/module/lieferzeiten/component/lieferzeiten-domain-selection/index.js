import template from './lieferzeiten-domain-selection.html.twig';
import {
    DEFAULT_GROUP_ID,
    DOMAIN_GROUPS,
    getChannelsForGroup,
    getDefaultDomainForGroup,
    normalizeDomainKey,
    normalizeGroupKey,
    resolveGroupKeyForDomain,
} from '../../utils/domain-source-mapping';

const STORAGE_CHANNEL_KEY = 'lieferzeitenManagementChannel';
const STORAGE_BEREICH_KEY = 'lieferzeitenManagementBereich';
const LEGACY_STORAGE_GROUP_KEY = 'lieferzeitenManagementGroup';
const LEGACY_STORAGE_DOMAIN_KEY = 'lieferzeitenManagementDomain';

Shopware.Component.register('lieferzeiten-domain-selection', {
    template,

    props: {
        value: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            selectedBereich: null,
            selectedChannel: normalizeDomainKey(this.value),
            persistSelection: false,
            isInternalUpdate: false,
        };
    },

    created() {
        this.loadStoredSelection();
    },

    watch: {
        value(newValue) {
            const normalizedChannel = normalizeDomainKey(newValue);
            if (!normalizedChannel || normalizedChannel === this.selectedChannel) {
                return;
            }

            this.applySelection(this.resolveInitialGroup(normalizedChannel, this.selectedBereich), normalizedChannel, false, false);
        },

        selectedBereich(newValue, oldValue) {
            if (this.isInternalUpdate || newValue === oldValue) {
                return;
            }

            if (!newValue) {
                this.selectedChannel = null;
                this.$emit('bereich-change', null);
                this.$emit('group-change', null);
                this.$emit('input', null);
                return;
            }

            const allowedChannels = getChannelsForGroup(newValue).map((channel) => channel.value);
            const nextChannel = allowedChannels.includes(this.selectedChannel)
                ? this.selectedChannel
                : getDefaultDomainForGroup(newValue);

            this.isInternalUpdate = true;
            this.selectedChannel = nextChannel;
            this.isInternalUpdate = false;

            this.$emit('bereich-change', newValue);
            this.$emit('group-change', newValue);
            this.$emit('input', nextChannel);
            this.persistDomainSelection(newValue, nextChannel);
        },

        selectedChannel(newValue, oldValue) {
            if (this.isInternalUpdate || !this.selectedBereich || !newValue || newValue === oldValue) {
                return;
            }

            this.$emit('input', newValue);
            this.persistDomainSelection(this.selectedBereich, newValue);
        },
    },

    computed: {
        groupOptions() {
            return DOMAIN_GROUPS.map((group) => ({
                value: group.id,
                label: this.$t(group.labelSnippet),
            }));
        },

        channelOptions() {
            return getChannelsForGroup(this.selectedBereich).map((channel) => ({
                value: channel.value,
                label: this.$t(channel.labelSnippet),
            }));
        },

        canSelectChannel() {
            return Boolean(this.selectedBereich);
        },
    },

    methods: {
        resolveInitialGroup(channel, fallbackGroup = null) {
            return resolveGroupKeyForDomain(channel)
                || normalizeGroupKey(fallbackGroup)
                || DEFAULT_GROUP_ID;
        },

        getStoredSelection(storage) {
            const rawBereich = storage.getItem(STORAGE_BEREICH_KEY);
            const rawLegacyGroup = storage.getItem(LEGACY_STORAGE_GROUP_KEY);
            const rawChannel = storage.getItem(STORAGE_CHANNEL_KEY);
            const rawLegacyDomain = storage.getItem(LEGACY_STORAGE_DOMAIN_KEY);

            const normalizedBereich = normalizeGroupKey(rawBereich || rawLegacyGroup);
            const normalizedChannel = normalizeDomainKey(rawChannel || rawLegacyDomain);

            if (!normalizedBereich && !normalizedChannel) {
                return null;
            }

            return {
                bereich: normalizedBereich || this.resolveInitialGroup(normalizedChannel),
                channel: normalizedChannel,
                requiresMigration: (!rawBereich && Boolean(rawLegacyGroup)) || (!rawChannel && Boolean(rawLegacyDomain)),
            };
        },

        loadStoredSelection() {
            const localSelection = this.getStoredSelection(localStorage);
            if (localSelection) {
                this.persistSelection = true;
                this.applySelection(localSelection.bereich, localSelection.channel, true, false);
                if (localSelection.requiresMigration) {
                    this.persistDomainSelection(localSelection.bereich, localSelection.channel);
                }
                return;
            }

            const sessionSelection = this.getStoredSelection(sessionStorage);
            if (sessionSelection) {
                this.persistSelection = false;
                this.applySelection(sessionSelection.bereich, sessionSelection.channel, true, false);
                if (sessionSelection.requiresMigration) {
                    this.persistDomainSelection(sessionSelection.bereich, sessionSelection.channel);
                }
                return;
            }

            const propChannel = normalizeDomainKey(this.value);
            if (propChannel) {
                this.applySelection(null, propChannel, true, false);
                return;
            }

            this.$emit('bereich-change', null);
            this.$emit('group-change', null);
            this.$emit('input', null);
        },

        applySelection(group, channel, emit = true, persist = true) {
            const resolvedGroup = this.resolveInitialGroup(channel, group);
            const allowedChannels = getChannelsForGroup(resolvedGroup).map((item) => item.value);
            const resolvedChannel = allowedChannels.includes(channel)
                ? channel
                : getDefaultDomainForGroup(resolvedGroup);

            this.isInternalUpdate = true;
            this.selectedBereich = resolvedGroup;
            this.selectedChannel = resolvedChannel;
            this.isInternalUpdate = false;

            if (emit) {
                this.$emit('bereich-change', resolvedGroup);
                this.$emit('group-change', resolvedGroup);
                this.$emit('input', resolvedChannel);
            }

            if (persist) {
                this.persistDomainSelection(resolvedGroup, resolvedChannel);
            }
        },

        persistDomainSelection(group, channel) {
            if (!group || !channel) {
                return;
            }

            const targetStorage = this.persistSelection ? localStorage : sessionStorage;
            const secondaryStorage = this.persistSelection ? sessionStorage : localStorage;

            targetStorage.setItem(STORAGE_BEREICH_KEY, group);
            targetStorage.setItem(LEGACY_STORAGE_GROUP_KEY, group);
            targetStorage.setItem(STORAGE_CHANNEL_KEY, channel);
            targetStorage.setItem(LEGACY_STORAGE_DOMAIN_KEY, channel);

            secondaryStorage.removeItem(STORAGE_BEREICH_KEY);
            secondaryStorage.removeItem(LEGACY_STORAGE_GROUP_KEY);
            secondaryStorage.removeItem(STORAGE_CHANNEL_KEY);
            secondaryStorage.removeItem(LEGACY_STORAGE_DOMAIN_KEY);
        },

        resetDomainSelection() {
            localStorage.removeItem(STORAGE_BEREICH_KEY);
            localStorage.removeItem(LEGACY_STORAGE_GROUP_KEY);
            localStorage.removeItem(STORAGE_CHANNEL_KEY);
            localStorage.removeItem(LEGACY_STORAGE_DOMAIN_KEY);
            sessionStorage.removeItem(STORAGE_BEREICH_KEY);
            sessionStorage.removeItem(LEGACY_STORAGE_GROUP_KEY);
            sessionStorage.removeItem(STORAGE_CHANNEL_KEY);
            sessionStorage.removeItem(LEGACY_STORAGE_DOMAIN_KEY);

            this.selectedBereich = null;
            this.selectedChannel = null;

            this.$emit('bereich-change', null);
            this.$emit('group-change', null);
            this.$emit('input', null);
        },
    },
});
