const { Component, Mixin } = Shopware;

Component.register('lieferzeiten-demo-data-button', {
    template: `
        <div class="lieferzeiten-demo-data-button">
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

    inject: ['lieferzeitenOrdersService'],

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
                const response = await this.lieferzeitenOrdersService.getDemoDataStatus();
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
                const response = await this.lieferzeitenOrdersService.toggleDemoData();
                const isRemoved = response?.action === 'removed';
                const hasSummary = Object.prototype.hasOwnProperty.call(response || {}, 'createdExternalOrders');
                this.hasDemoData = isRemoved ? false : (hasSummary ? true : Boolean(response?.hasDemoData));

                if (isRemoved) {
                    const removed = response?.deleted || {};
                    const totalRemoved = Object.values(removed).reduce((sum, value) => sum + Number(value || 0), 0);

                    this.createNotificationSuccess({
                        title: 'Demo-Daten entfernt',
                        message: totalRemoved > 0
                            ? `${totalRemoved} Demo-Datensätze wurden entfernt.`
                            : 'Keine Demo-Daten waren vorhanden.',
                    });

                    return;
                }

                if (hasSummary) {
                    const missing = Array.isArray(response?.missing) ? response.missing.length : 0;
                    this.createNotificationSuccess({
                        title: 'Demo-Daten gespeichert',
                        message: `${response?.createdExternalOrders ?? 0} externe Bestellungen, ${response?.createdLieferzeiten ?? 0} Lieferzeiten-Datensätze, ${response?.linked ?? 0} verknüpft${missing > 0 ? `, ${missing} fehlend` : ''}.`,
                    });

                    return;
                }

                const created = response?.created || {};
                const totalCreated = Object.values(created).reduce((sum, value) => sum + Number(value || 0), 0);

                this.createNotificationSuccess({
                    title: 'Demo-Daten gespeichert',
                    message: totalCreated > 0
                        ? `${totalCreated} Demo-Datensätze wurden gespeichert.`
                        : 'Keine neuen Demo-Datensätze wurden gespeichert.',
                });
            } catch (error) {
                this.createNotificationError({
                    title: 'Demo-Daten',
                    message: error?.response?.data?.message || error?.message || 'Aktion konnte nicht ausgeführt werden.',
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
