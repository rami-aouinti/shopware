import template from './lieferzeiten-domain-selection.html.twig';

const STORAGE_KEY = 'lieferzeitenManagementDomain';

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
            selectedDomain: this.value,
            persistSelection: false,
            showDomainModal: false,
            domains: [
                { value: 'First Medical', label: 'First Medical' },
                { value: 'E-Commerce', label: 'E-Commerce' },
                { value: 'Medical Solutions', label: 'Medical Solutions' },
            ],
        };
    },

    created() {
        this.loadStoredDomain();
    },

    watch: {
        value(newValue) {
            this.selectedDomain = newValue;
        },
        selectedDomain(newValue) {
            this.$emit('input', newValue);
            this.persistDomain(newValue);
        },
        persistSelection() {
            this.persistDomain(this.selectedDomain);
        },
    },

    methods: {
        loadStoredDomain() {
            const localValue = localStorage.getItem(STORAGE_KEY);
            if (localValue) {
                this.persistSelection = true;
                this.selectedDomain = localValue;
                return;
            }

            const sessionValue = sessionStorage.getItem(STORAGE_KEY);
            if (sessionValue) {
                this.persistSelection = false;
                this.selectedDomain = sessionValue;
                return;
            }

            this.showDomainModal = true;
        },

        persistDomain(value) {
            if (!value) {
                return;
            }

            if (this.persistSelection) {
                localStorage.setItem(STORAGE_KEY, value);
                sessionStorage.removeItem(STORAGE_KEY);
            } else {
                sessionStorage.setItem(STORAGE_KEY, value);
                localStorage.removeItem(STORAGE_KEY);
            }
        },

        confirmDomainSelection() {
            if (!this.selectedDomain) {
                return;
            }

            this.persistDomain(this.selectedDomain);
            this.showDomainModal = false;
        },

        resetDomainSelection() {
            localStorage.removeItem(STORAGE_KEY);
            sessionStorage.removeItem(STORAGE_KEY);
            this.selectedDomain = null;
            this.showDomainModal = true;
        },
    },
});
