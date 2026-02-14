import template from './lieferzeiten-domain-selection.html.twig';
import {
    DEFAULT_DOMAIN_KEY,
    DEFAULT_GROUP_ID,
    DOMAIN_GROUPS,
    getChannelsForGroup,
    getDefaultDomainForGroup,
    normalizeDomainKey,
    normalizeGroupKey,
    resolveGroupKeyForDomain,
} from '../../utils/domain-source-mapping';

const STORAGE_DOMAIN_KEY = 'lieferzeitenManagementDomain';
const STORAGE_GROUP_KEY = 'lieferzeitenManagementGroup';

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
            selectedGroup: null,
            selectedDomain: normalizeDomainKey(this.value),
            persistSelection: false,
            showDomainModal: false,
            modalStep: 1,
        };
    },

    created() {
        this.loadStoredSelection();
    },

    watch: {
        value(newValue) {
            const normalizedDomain = normalizeDomainKey(newValue);
            this.selectedDomain = normalizedDomain;
            this.selectedGroup = this.resolveInitialGroup(normalizedDomain, this.selectedGroup);
        },
        selectedGroup(newValue) {
            if (!newValue) {
                this.selectedDomain = null;
                return;
            }

            const channelExists = getChannelsForGroup(newValue)
                .some((channel) => channel.value === this.selectedDomain);

            if (!channelExists) {
                this.selectedDomain = getDefaultDomainForGroup(newValue);
            }
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
            return getChannelsForGroup(this.selectedGroup).map((channel) => ({
                value: channel.value,
                label: this.$t(channel.labelSnippet),
            }));
        },

        canGoToChannelStep() {
            return Boolean(this.selectedGroup);
        },

        canConfirmSelection() {
            return Boolean(this.selectedGroup && this.selectedDomain);
        },
    },

    methods: {
        resolveInitialGroup(domain, fallbackGroup = null) {
            return resolveGroupKeyForDomain(domain)
                || normalizeGroupKey(fallbackGroup)
                || DEFAULT_GROUP_ID;
        },

        loadStoredSelection() {
            const localGroup = normalizeGroupKey(localStorage.getItem(STORAGE_GROUP_KEY));
            const localDomain = normalizeDomainKey(localStorage.getItem(STORAGE_DOMAIN_KEY));
            if (localGroup || localDomain) {
                this.persistSelection = true;
                this.applySelection(localGroup, localDomain, true, false);
                return;
            }

            const sessionGroup = normalizeGroupKey(sessionStorage.getItem(STORAGE_GROUP_KEY));
            const sessionDomain = normalizeDomainKey(sessionStorage.getItem(STORAGE_DOMAIN_KEY));
            if (sessionGroup || sessionDomain) {
                this.persistSelection = false;
                this.applySelection(sessionGroup, sessionDomain, true, false);
                return;
            }

            const propDomain = normalizeDomainKey(this.value);
            if (propDomain) {
                this.applySelection(null, propDomain, true, false);
                return;
            }

            this.applySelection(DEFAULT_GROUP_ID, DEFAULT_DOMAIN_KEY, false, false);
            this.showDomainModal = true;
            this.modalStep = 1;
        },

        applySelection(group, domain, emit = true, persist = true) {
            const resolvedGroup = this.resolveInitialGroup(domain, group);
            const allowedDomains = getChannelsForGroup(resolvedGroup).map((channel) => channel.value);
            const resolvedDomain = allowedDomains.includes(domain)
                ? domain
                : getDefaultDomainForGroup(resolvedGroup);

            this.selectedGroup = resolvedGroup;
            this.selectedDomain = resolvedDomain;

            if (emit && resolvedDomain) {
                this.$emit('input', resolvedDomain);
            }

            if (persist) {
                this.persistDomainSelection(resolvedGroup, resolvedDomain);
            }
        },

        persistDomainSelection(group, domain) {
            if (!group || !domain) {
                return;
            }

            const targetStorage = this.persistSelection ? localStorage : sessionStorage;
            const secondaryStorage = this.persistSelection ? sessionStorage : localStorage;

            targetStorage.setItem(STORAGE_GROUP_KEY, group);
            targetStorage.setItem(STORAGE_DOMAIN_KEY, domain);

            secondaryStorage.removeItem(STORAGE_GROUP_KEY);
            secondaryStorage.removeItem(STORAGE_DOMAIN_KEY);
        },

        goToChannelStep() {
            if (!this.canGoToChannelStep) {
                return;
            }

            this.modalStep = 2;
        },

        goToGroupStep() {
            this.modalStep = 1;
        },

        confirmDomainSelection() {
            if (!this.canConfirmSelection) {
                return;
            }

            this.applySelection(this.selectedGroup, this.selectedDomain, true);
            this.showDomainModal = false;
        },

        resetDomainSelection() {
            localStorage.removeItem(STORAGE_GROUP_KEY);
            localStorage.removeItem(STORAGE_DOMAIN_KEY);
            sessionStorage.removeItem(STORAGE_GROUP_KEY);
            sessionStorage.removeItem(STORAGE_DOMAIN_KEY);

            this.applySelection(DEFAULT_GROUP_ID, DEFAULT_DOMAIN_KEY, true);
            this.showDomainModal = true;
            this.modalStep = 1;
        },
    },
});
