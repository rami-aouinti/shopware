<script>
import { Component, Context, Criteria, Service, Utils } from '@pickware/shopware-adapter';
import { createNotification } from '@pickware/shopware-administration-notification';

import { BulkEditDocumentStoreNamespace } from './bulk-edit-document-store.js';

const { mapGetters } = Component.getComponentHelper();
const { chunk: chunkArray } = Utils.array;
export default {
    overrideFrom: 'sw-bulk-edit-save-modal-process',

    data() {
        return {
            pwErpBulkEditSaveModalErrorNumbers: [],
        };
    },

    computed: {
        ...mapGetters(BulkEditDocumentStoreNamespace, {
            pwErpDocumentTypeConfigs: 'documentTypeConfigs',
        }),

        createDocumentPayload() {
            const payload = this.$super('createDocumentPayload');

            this.selectedIds.forEach((selectedId) => {
                this.pwErpDocumentTypeConfigs?.forEach((documentTypeConfig) => {
                    if (documentTypeConfig) {
                        payload.push({
                            ...documentTypeConfig,
                            orderId: selectedId,
                        });
                    }
                });
            });

            return payload;
        },

        selectedDocumentTypes() {
            const selectedDocumentTypes = this.$super('selectedDocumentTypes');

            this.pwErpDocumentTypeConfigs.forEach((documentTypeConfig) => {
                const selectedDocumentType = this.documentTypes.find((documentType) => documentTypeConfig.type === documentType.technicalName);

                if (selectedDocumentType) {
                    selectedDocumentTypes.push(selectedDocumentType);
                }
            });

            return selectedDocumentTypes;
        },
    },

    methods: {
        async pwErpGetOrderNumbers(orderIds) {
            const criteria = new Criteria(1, 500);
            criteria.setIds(orderIds);

            const orderResponse = await Service('repositoryFactory')
                .create('order')
                .search(criteria, Context.api);

            return orderResponse.map((order) => order.orderNumber);
        },

        async createdComponent(...args) {
            this.updateButtons();
            this.setTitle();
            await this.createDocuments(...args);

            // We cannot call this.$super() here, because we need the notification to be shown before the
            // changes-apply event is emitted. Otherwise the notification will not be shown.
            if (this.pwErpBulkEditSaveModalErrorNumbers.length > 0) {
                const orderNumberString = this.pwErpBulkEditSaveModalErrorNumbers.sort().join(', ');
                createNotification({
                    title: this.$t('sw-bulk-edit-order-document-generation-failed-modal.title'),
                    message: this.$t('sw-bulk-edit-order-document-generation-failed-modal.content')
                            + '<br/> ' + orderNumberString,
                    autoClose: false,
                    variant: 'warning',
                });
            }

            await this.$emit('changes-apply');
        },

        // We override the 'createDocument' function of sw-bulk-edit-order because we do not get a response object from
        // shopware when documents are generated, but we need it to handle errors and give user feedback when generation
        // fails
        async createDocument(documentType, payload) {
            const erroneousOrderIds = [];
            if (payload.length <= this.requestsPerPayload) {
                const res = await this.orderDocumentApiService.generate(documentType, payload);

                if (Object.keys(res.data.errors).length > 0) {
                    Object.keys(res.data.errors).forEach((orderId) => erroneousOrderIds.push(orderId));
                    this.pwErpBulkEditSaveModalErrorNumbers.push(...await this.pwErpGetOrderNumbers(
                        erroneousOrderIds,
                    ));
                }

                this.$set(this.document[documentType], 'isReached', 100);

                return res;
            }

            const chunkedPayload = chunkArray(payload, this.requestsPerPayload);
            const percentages = Math.round(100 / chunkedPayload.length);

            const documents = await Promise.all(
                chunkedPayload.map(async (item) => {
                    const res = await this.orderDocumentApiService.generate(documentType, item);
                    this.$set(
                        this.document[documentType],
                        'isReached',
                        this.document[documentType].isReached + percentages,
                    );

                    if (Object.keys(res.data.errors).length > 0) {
                        Object.keys(res.data.errors).forEach((orderId) => erroneousOrderIds.push(orderId));
                    }
                }),
            );
            if (erroneousOrderIds.length > 0) {
                this.pwErpBulkEditSaveModalErrorNumbers.push(...await this.pwErpGetOrderNumbers(erroneousOrderIds));
            }
            this.$set(this.document[documentType], 'isReached', 100);

            return documents;
        },
    },
};
</script>
<i18n>
{
    "de-DE": {
        "sw-bulk-edit-order-document-generation-failed-modal": {
            "title": "Fehler",
            "content": "Das Generieren von Dokumenten ist für manche Bestellungen fehlgeschlagen: "
        }
    },
    "en-GB": {
        "sw-bulk-edit-order-document-generation-failed-modal": {
            "title": "Error",
            "content": "Generating documents has failed for some orders: "
        }
    }
}
</i18n>
