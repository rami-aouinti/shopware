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
            draftBereich: null,
            persistSelection: false,
            showDomainModal: false,
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

        canConfirmGroupSelection() {
            return Boolean(this.draftBereich);
        },
    },

    methods: {
        resolveInitialGroup(channel, fallbackGroup = null) {
            return resolveGroupKeyForDomain(channel)
                || normalizeGroupKey(fallbackGroup)
                || DEFAULT_GROUP_ID;
        },

        loadStoredSelection() {
            const localGroup = normalizeGroupKey(localStorage.getItem(STORAGE_BEREICH_KEY) || localStorage.getItem(LEGACY_STORAGE_GROUP_KEY));
            const localChannel = normalizeDomainKey(localStorage.getItem(STORAGE_CHANNEL_KEY) || localStorage.getItem(LEGACY_STORAGE_DOMAIN_KEY));
            if (localGroup || localChannel) {
                this.persistSelection = true;
                this.applySelection(localGroup, localChannel, true, false);
                return;
            }

            const sessionGroup = normalizeGroupKey(sessionStorage.getItem(STORAGE_BEREICH_KEY) || sessionStorage.getItem(LEGACY_STORAGE_GROUP_KEY));
            const sessionChannel = normalizeDomainKey(sessionStorage.getItem(STORAGE_CHANNEL_KEY) || sessionStorage.getItem(LEGACY_STORAGE_DOMAIN_KEY));
            if (sessionGroup || sessionChannel) {
                this.persistSelection = false;
                this.applySelection(sessionGroup, sessionChannel, true, false);
                return;
            }

            const propChannel = normalizeDomainKey(this.value);
            if (propChannel) {
                this.applySelection(null, propChannel, true, false);
                return;
            }

            this.showDomainModal = true;
            this.draftBereich = null;
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

            secondaryStorage.removeItem(STORAGE_BEREICH_KEY);
            secondaryStorage.removeItem(LEGACY_STORAGE_GROUP_KEY);
            secondaryStorage.removeItem(STORAGE_CHANNEL_KEY);
            secondaryStorage.removeItem(LEGACY_STORAGE_DOMAIN_KEY);
        },

        confirmGroupSelection() {
            if (!this.canConfirmGroupSelection) {
                return;
            }

            const nextChannel = getChannelsForGroup(this.draftBereich)
                .some((channel) => channel.value === this.selectedChannel)
                ? this.selectedChannel
                : getDefaultDomainForGroup(this.draftBereich);

            this.applySelection(this.draftBereich, nextChannel, true);
            this.showDomainModal = false;
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
            this.showDomainModal = true;
            this.draftBereich = null;

            this.$emit('bereich-change', null);
            this.$emit('group-change', null);
            this.$emit('input', null);
        },
    },
});
