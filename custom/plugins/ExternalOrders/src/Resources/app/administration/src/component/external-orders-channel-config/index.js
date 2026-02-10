const { Component, Mixin } = Shopware;

Component.register('external-orders-channel-config', {
    template: `
        <div class="external-orders-channel-config">
            <label class="sw-field__label" v-if="label">{{ label }}</label>

            <div class="external-orders-channel-config__grid">
                <button
                    v-for="channel in channels"
                    :key="channel.id"
                    type="button"
                    class="external-orders-channel-config__item"
                    @click="openChannelModal(channel)"
                >
                    <span class="external-orders-channel-config__logo">
                        <img :src="channel.logo" :alt="channel.label" />
                    </span>
                    <span class="external-orders-channel-config__title">{{ channel.label }}</span>
                </button>
            </div>

            <div class="external-orders-channel-config__cron">
                <sw-button variant="primary" size="small" :disabled="isRunningCron" @click="runSyncNow">
                    Cron job jetzt starten
                </sw-button>
                <div class="external-orders-channel-config__cron-status">
                    Letzter Cronjob:
                    <strong>{{ cronStatusLabel }}</strong>
                </div>
            </div>

            <sw-modal
                v-if="showModal"
                :title="modalTitle"
                @modal-close="closeModal"
            >
                <sw-text-field
                    v-model="channelForm.apiUrl"
                    label="API URL"
                    placeholder="https://..."
                />
                <sw-text-field
                    v-model="channelForm.apiToken"
                    label="API Token"
                    placeholder="Token"
                />

                <template #modal-footer>
                    <sw-button size="small" @click="closeModal">Schließen</sw-button>
                    <sw-button variant="primary" size="small" :disabled="isSaving" @click="saveChannelConfig">
                        Save & Close
                    </sw-button>
                </template>
            </sw-modal>

            <div v-if="helpText" class="sw-field__help-text">{{ helpText }}</div>
        </div>
    `,

    inject: ['systemConfigApiService', 'externalOrderService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        element: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            showModal: false,
            selectedChannel: null,
            isSaving: false,
            isRunningCron: false,
            cronStatus: {
                status: null,
                isSuccess: null,
                lastExecutionTime: null,
            },
            channelForm: {
                apiUrl: '',
                apiToken: '',
            },
            channels: [
                { id: 'b2b', label: 'First-medical-shop.de', logo: 'http://controlling.first-medical.de:8480/assets/images/b2b1.png', urlKey: 'externalOrdersApiUrlB2b', tokenKey: 'externalOrdersApiTokenB2b' },
                { id: 'ebay_de', label: 'Ebay.DE', logo: 'http://controlling.first-medical.de:8480/assets/images/ebay1.png', urlKey: 'externalOrdersApiUrlEbayDe', tokenKey: 'externalOrdersApiTokenEbayDe' },
                { id: 'kaufland', label: 'Kaufland', logo: 'http://controlling.first-medical.de:8480/assets/images/kaufland1.png', urlKey: 'externalOrdersApiUrlKaufland', tokenKey: 'externalOrdersApiTokenKaufland' },
                { id: 'ebay_at', label: 'Ebay.AT', logo: 'http://controlling.first-medical.de:8480/assets/images/ebayAT.png', urlKey: 'externalOrdersApiUrlEbayAt', tokenKey: 'externalOrdersApiTokenEbayAt' },
                { id: 'zonami', label: 'Zonami', logo: 'http://controlling.first-medical.de:8480/assets/images/zonami1.png', urlKey: 'externalOrdersApiUrlZonami', tokenKey: 'externalOrdersApiTokenZonami' },
                { id: 'peg', label: 'PEG', logo: 'http://controlling.first-medical.de:8480/assets/images/peg2.png', urlKey: 'externalOrdersApiUrlPeg', tokenKey: 'externalOrdersApiTokenPeg' },
                { id: 'bezb', label: 'BEZB', logo: 'http://controlling.first-medical.de:8480/assets/images/bezb1.png', urlKey: 'externalOrdersApiUrlBezb', tokenKey: 'externalOrdersApiTokenBezb' },
            ],
        };
    },

    computed: {
        modalTitle() {
            return `Zugangsdaten: ${this.selectedChannel?.label || ''}`;
        },
        label() {
            return this.element?.label ?? '';
        },
        helpText() {
            return this.element?.helpText ?? '';
        },
        cronStatusLabel() {
            if (!this.cronStatus.lastExecutionTime) {
                return 'Noch kein Lauf erkannt';
            }

            const executedAt = this.formatDate(this.cronStatus.lastExecutionTime);
            const state = this.cronStatus.isSuccess ? 'Erfolgreich' : 'Fehlgeschlagen';

            return `${state} (${executedAt})`;
        },
    },

    created() {
        this.loadCronStatus();
    },

    methods: {
        getConfigKey(key) {
            return `ExternalOrders.config.${key}`;
        },

        async openChannelModal(channel) {
            this.selectedChannel = channel;
            const values = await this.systemConfigApiService.getValues('ExternalOrders.config');

            this.channelForm.apiUrl = values[this.getConfigKey(channel.urlKey)] || '';
            this.channelForm.apiToken = values[this.getConfigKey(channel.tokenKey)] || '';
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.selectedChannel = null;
            this.channelForm.apiUrl = '';
            this.channelForm.apiToken = '';
        },

        async saveChannelConfig() {
            if (!this.selectedChannel || this.isSaving) {
                return;
            }

            this.isSaving = true;

            try {
                await this.systemConfigApiService.saveValues({
                    [this.getConfigKey(this.selectedChannel.urlKey)]: this.channelForm.apiUrl,
                    [this.getConfigKey(this.selectedChannel.tokenKey)]: this.channelForm.apiToken,
                });

                this.createNotificationSuccess({
                    title: 'Gespeichert',
                    message: `${this.selectedChannel.label} wurde aktualisiert.`,
                });
                this.closeModal();
            } catch (error) {
                this.createNotificationError({
                    title: 'Speichern fehlgeschlagen',
                    message: error?.message || 'Die Zugangsdaten konnten nicht gespeichert werden.',
                });
            } finally {
                this.isSaving = false;
            }
        },

        async loadCronStatus() {
            try {
                const response = await this.externalOrderService.getSyncStatus();
                this.cronStatus = {
                    status: response?.status || null,
                    isSuccess: response?.isSuccess ?? null,
                    lastExecutionTime: response?.lastExecutionTime || null,
                };
            } catch (error) {
                this.cronStatus = {
                    status: null,
                    isSuccess: null,
                    lastExecutionTime: null,
                };
            }
        },

        async runSyncNow() {
            if (this.isRunningCron) {
                return;
            }

            this.isRunningCron = true;

            try {
                const response = await this.externalOrderService.runSyncNow();

                this.createNotificationSuccess({
                    title: 'Cronjob gestartet',
                    message: response?.success ? 'Der Sync wurde erfolgreich ausgeführt.' : 'Der Sync wurde ausgeführt.',
                });
            } catch (error) {
                this.createNotificationError({
                    title: 'Cronjob fehlgeschlagen',
                    message: error?.message || 'Der Cronjob konnte nicht gestartet werden.',
                });
            } finally {
                this.isRunningCron = false;
                await this.loadCronStatus();
            }
        },

        formatDate(date) {
            const parsedDate = new Date(date);
            if (Number.isNaN(parsedDate.getTime())) {
                return date;
            }

            return parsedDate.toLocaleString('de-DE');
        },
    },
});
