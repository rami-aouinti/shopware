const { Component, Mixin } = Shopware;

import './external-orders-channel-config.scss';
import channelIllustration from './images/external-orders-hero.svg';

Component.register('external-orders-channel-config', {
    template: `
        <div class="external-orders-channel-config">
            <div class="external-orders-channel-config__header">
                <div>
                    <label class="sw-field__label" v-if="label">{{ label }}</label>
                    <h3 class="external-orders-channel-config__headline">External Orders Hub</h3>
                    <p class="external-orders-channel-config__subtitle">
                        Verwalte alle Zugangsdaten zentral und starte den Sync mit einem Klick.
                    </p>
                </div>
                <img
                    class="external-orders-channel-config__hero-image"
                    :src="channelIllustration"
                    alt="External orders"
                />
            </div>

            <div class="external-orders-channel-config__panel">
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
                <template v-if="selectedChannel?.id !== 'san6'">
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
                </template>

                <template v-if="selectedChannel?.id === 'san6'">
                    <sw-text-field v-model="channelForm.san6BaseUrl" label="SAN6 Base URL" placeholder="https://...:4443" />
                    <sw-text-field v-model="channelForm.san6Company" label="SAN6 Company" />
                    <sw-text-field v-model="channelForm.san6Product" label="SAN6 Product" />
                    <sw-single-select
                        v-model:value="channelForm.san6Mandant"
                        label="SAN6 Mandant"
                        :options="san6MandantOptions"
                    />
                    <sw-text-field v-model="channelForm.san6Sys" label="SAN6 Sys" />
                    <sw-text-field v-model="channelForm.san6Authentifizierung" label="SAN6 Authentifizierung" />
                    <sw-text-field v-model="channelForm.san6ReadFunction" label="SAN6 Funktion (Lesen)" placeholder="API-AUFTRAEGE" />
                    <sw-text-field v-model="channelForm.san6WriteFunction" label="SAN6 Funktion (Schreiben)" placeholder="API-AUFTRAGNEU2" />
                    <sw-single-select
                        v-model:value="channelForm.san6SendStrategy"
                        label="SAN6 Versandstrategie"
                        :options="san6SendStrategyOptions"
                    />
                </template>

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
            channelIllustration,
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
                san6BaseUrl: '',
                san6Company: '',
                san6Product: '',
                san6Mandant: 'Schule',
                san6Sys: '',
                san6Authentifizierung: '',
                san6ReadFunction: 'API-AUFTRAEGE',
                san6WriteFunction: 'API-AUFTRAGNEU2',
                san6SendStrategy: 'filetransferurl',
            },
            channels: [
                { id: 'b2b', label: 'First-medical-shop.de', logo: 'http://controlling.first-medical.de:8480/assets/images/b2b1.png', urlKey: 'externalOrdersApiUrlB2b', tokenKey: 'externalOrdersApiTokenB2b' },
                { id: 'ebay_de', label: 'Ebay.DE', logo: 'http://controlling.first-medical.de:8480/assets/images/ebay1.png', urlKey: 'externalOrdersApiUrlEbayDe', tokenKey: 'externalOrdersApiTokenEbayDe' },
                { id: 'kaufland', label: 'Kaufland', logo: 'http://controlling.first-medical.de:8480/assets/images/kaufland1.png', urlKey: 'externalOrdersApiUrlKaufland', tokenKey: 'externalOrdersApiTokenKaufland' },
                { id: 'ebay_at', label: 'Ebay.AT', logo: 'http://controlling.first-medical.de:8480/assets/images/ebayAT.png', urlKey: 'externalOrdersApiUrlEbayAt', tokenKey: 'externalOrdersApiTokenEbayAt' },
                { id: 'zonami', label: 'Zonami', logo: 'http://controlling.first-medical.de:8480/assets/images/zonami1.png', urlKey: 'externalOrdersApiUrlZonami', tokenKey: 'externalOrdersApiTokenZonami' },
                { id: 'peg', label: 'PEG', logo: 'http://controlling.first-medical.de:8480/assets/images/peg2.png', urlKey: 'externalOrdersApiUrlPeg', tokenKey: 'externalOrdersApiTokenPeg' },
                { id: 'bezb', label: 'BEZB', logo: 'http://controlling.first-medical.de:8480/assets/images/bezb1.png', urlKey: 'externalOrdersApiUrlBezb', tokenKey: 'externalOrdersApiTokenBezb' },
                {
                    id: 'san6',
                    label: 'SAN6',
                    logo: 'http://controlling.first-medical.de:8480/assets/images/bezb1.png',
                    urlKey: 'externalOrdersSan6BaseUrl',
                    tokenKey: 'externalOrdersSan6Authentifizierung',
                },
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
        san6MandantOptions() {
            return [
                { value: 'Schule', label: 'Schule' },
                { value: 'Zentrale', label: 'Zentrale' },
            ];
        },
        san6SendStrategyOptions() {
            return [
                { value: 'filetransferurl', label: 'filetransferurl' },
                { value: 'post-xml', label: 'post-xml' },
            ];
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

            if (channel.id === 'san6') {
                this.channelForm.san6BaseUrl = this.channelForm.apiUrl;
                this.channelForm.san6Company = values[this.getConfigKey('externalOrdersSan6Company')] || '';
                this.channelForm.san6Product = values[this.getConfigKey('externalOrdersSan6Product')] || '';
                this.channelForm.san6Mandant = values[this.getConfigKey('externalOrdersSan6Mandant')] || 'Schule';
                this.channelForm.san6Sys = values[this.getConfigKey('externalOrdersSan6Sys')] || '';
                this.channelForm.san6Authentifizierung = this.channelForm.apiToken;
                this.channelForm.san6ReadFunction = values[this.getConfigKey('externalOrdersSan6ReadFunction')] || 'API-AUFTRAEGE';
                this.channelForm.san6WriteFunction = values[this.getConfigKey('externalOrdersSan6WriteFunction')] || 'API-AUFTRAGNEU2';
                this.channelForm.san6SendStrategy = values[this.getConfigKey('externalOrdersSan6SendStrategy')] || 'filetransferurl';
            }

            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.selectedChannel = null;
            this.channelForm.apiUrl = '';
            this.channelForm.apiToken = '';
            this.channelForm.san6BaseUrl = '';
            this.channelForm.san6Company = '';
            this.channelForm.san6Product = '';
            this.channelForm.san6Mandant = 'Schule';
            this.channelForm.san6Sys = '';
            this.channelForm.san6Authentifizierung = '';
            this.channelForm.san6ReadFunction = 'API-AUFTRAEGE';
            this.channelForm.san6WriteFunction = 'API-AUFTRAGNEU2';
            this.channelForm.san6SendStrategy = 'filetransferurl';
        },

        async saveChannelConfig() {
            if (!this.selectedChannel || this.isSaving) {
                return;
            }

            this.isSaving = true;

            try {
                const payload = this.selectedChannel.id === 'san6'
                    ? {
                        [this.getConfigKey(this.selectedChannel.urlKey)]: this.channelForm.san6BaseUrl,
                        [this.getConfigKey(this.selectedChannel.tokenKey)]: this.channelForm.san6Authentifizierung,
                    }
                    : {
                        [this.getConfigKey(this.selectedChannel.urlKey)]: this.channelForm.apiUrl,
                        [this.getConfigKey(this.selectedChannel.tokenKey)]: this.channelForm.apiToken,
                    };

                if (this.selectedChannel.id === 'san6') {
                    payload[this.getConfigKey('externalOrdersSan6BaseUrl')] = this.channelForm.san6BaseUrl;
                    payload[this.getConfigKey('externalOrdersSan6Company')] = this.channelForm.san6Company;
                    payload[this.getConfigKey('externalOrdersSan6Product')] = this.channelForm.san6Product;
                    payload[this.getConfigKey('externalOrdersSan6Mandant')] = this.channelForm.san6Mandant;
                    payload[this.getConfigKey('externalOrdersSan6Sys')] = this.channelForm.san6Sys;
                    payload[this.getConfigKey('externalOrdersSan6Authentifizierung')] = this.channelForm.san6Authentifizierung;
                    payload[this.getConfigKey('externalOrdersSan6ReadFunction')] = this.channelForm.san6ReadFunction;
                    payload[this.getConfigKey('externalOrdersSan6WriteFunction')] = this.channelForm.san6WriteFunction;
                    payload[this.getConfigKey('externalOrdersSan6SendStrategy')] = this.channelForm.san6SendStrategy;
                }

                await this.systemConfigApiService.saveValues(payload);

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
