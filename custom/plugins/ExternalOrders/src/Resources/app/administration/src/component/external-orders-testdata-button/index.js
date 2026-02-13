const { Component, Mixin } = Shopware;

Component.register('external-orders-testdata-button', {
    template: `
        <div class="external-orders-testdata-button">
            <label class="sw-field__label" v-if="label">{{ label }}</label>
            <sw-button
                variant="primary"
                size="small"
                :disabled="isLoading"
                @click="onToggleDemoData"
            >
                {{ buttonLabel }}
            </sw-button>
            <div v-if="helpText" class="sw-field__help-text">
                {{ helpText }}
            </div>
        </div>
    `,

    inject: ['externalOrderService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        element: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: false,
            hasDemoData: false,
        };
    },

    computed: {
        label() {
            return this.element?.label ?? '';
        },
        helpText() {
            return this.element?.helpText ?? '';
        },
        buttonLabel() {
            return this.hasDemoData ? 'Demo-Daten entfernen' : 'Demo-Daten speichern';
        },
    },

    async created() {
        await this.loadStatus();
    },

    methods: {
        async loadStatus() {
            this.isLoading = true;

            try {
                const response = await this.externalOrderService.getTestDataStatus();
                this.hasDemoData = Boolean(response?.hasDemoData);
            } catch (error) {
                this.createNotificationError({
                    title: 'Demo-Daten Status',
                    message: error?.message || 'Status konnte nicht geladen werden.',
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onToggleDemoData() {
            if (this.isLoading) {
                return;
            }

            this.isLoading = true;

            try {
                const response = await this.externalOrderService.toggleTestData();
                this.hasDemoData = Boolean(response?.hasDemoData);

                if (response?.action === 'removed') {
                    const removed = response?.removed ?? 0;
                    this.createNotificationSuccess({
                        title: 'Demo-Daten entfernt',
                        message: removed > 0
                            ? `${removed} Demo-Bestellungen wurden entfernt.`
                            : 'Keine Demo-Bestellungen waren vorhanden.',
                    });

                    return;
                }

                const inserted = response?.inserted ?? 0;
                this.createNotificationSuccess({
                    title: 'Demo-Daten gespeichert',
                    message: inserted > 0
                        ? `Es wurden ${inserted} Demo-Bestellungen gespeichert.`
                        : 'Keine neuen Demo-Bestellungen wurden gespeichert.',
                });
            } catch (error) {
                this.createNotificationError({
                    title: 'Demo-Daten',
                    message: error?.message || 'Aktion konnte nicht ausgeführt werden.',
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
