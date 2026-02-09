const { Component, Mixin } = Shopware;

Component.register('external-orders-testdata-button', {
    template: `
        <div class="external-orders-testdata-button">
            <label class="sw-field__label" v-if="label">{{ label }}</label>
            <sw-button
                variant="primary"
                size="small"
                :disabled="isSeeding"
                @click="seedTestData"
            >
                Testdaten speichern
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
            isSeeding: false,
        };
    },

    computed: {
        label() {
            return this.element?.label ?? '';
        },
        helpText() {
            return this.element?.helpText ?? '';
        },
    },

    methods: {
        async seedTestData() {
            if (this.isSeeding) {
                return;
            }

            this.isSeeding = true;

            try {
                const response = await this.externalOrderService.seedTestData();
                const inserted = response?.inserted ?? 0;
                this.createNotificationSuccess({
                    title: 'Testdaten gespeichert',
                    message: inserted > 0
                        ? `Es wurden ${inserted} Testbestellungen gespeichert.`
                        : 'Keine neuen Testbestellungen wurden gespeichert.',
                });
            } catch (error) {
                this.createNotificationError({
                    title: 'Testdaten konnten nicht gespeichert werden',
                    message: error?.message || 'Bitte prüfen Sie die Konfiguration der externen APIs.',
                });
            } finally {
                this.isSeeding = false;
            }
        },
    },
});
