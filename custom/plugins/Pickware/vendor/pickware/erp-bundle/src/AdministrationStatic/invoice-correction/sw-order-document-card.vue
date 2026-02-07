<!-- eslint-disable vue/valid-v-slot !-->
<template>
    <!-- Add 'config.documentNumber' template somewhere in the grid columns template. It does not exist in the parent
         template and therefore we cannot overwrite it - we have to _add_ it. -->
    {% block sw_order_document_card_grid_column_date %}
    {% parent %}

    <template #[`column-config.documentNumber`]="{ item }">
        {{ pickwareErpGetCustomDocumentLabel(item) }}
    </template>
    {% endblock %}

    {% block sw_order_document_card_grid_action_mark_unsent %}
    {% parent %}

    <sw-context-menu-item
        v-if="pickwareErpIsInvoiceCorrection(item)"
        variant="danger"
        :disabled="!pickwareErpIsInvoiceCorrectionDeletable(item)"
        @click="pickwareErpDeleteInvoiceCorrection(item)"
    >
        {{ $t('sw-order-document-card.pw-erp-invoice-correction.delete-document.label') }}
    </sw-context-menu-item>
    {% endblock %}
</template>

<script>
import { Context, Criteria } from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';
import { createErrorNotification, createSuccessNotification } from '@pickware/shopware-administration-notification';
import {
    DocumentTypeTechnicalNames, ShopwareDocumentTypeTechnicalName,
} from '@pickware-erp-bundle/document/document-types.js';
import get from 'lodash/get';

export default {
    overrideFrom: 'sw-order-document-card',

    inject: [
        'repositoryFactory',
    ],

    computed: {
        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            if (!PickwareFeature.isActive('pickware-erp.feature.invoice-correction')) {
                // Remove pickware_erp_invoice_correction (Rechnungskorrektur) from select options
                criteria.addFilter(
                    Criteria.not(
                        'AND',
                        [
                            Criteria.equals('technicalName', DocumentTypeTechnicalNames.INVOICE_CORRECTION),
                        ],
                    ),
                );
            }

            return criteria;
        },
    },

    methods: {
        async createdComponent() {
            this.$super('createdComponent');
            await this.pickwareErpOpenDocumentCreationModalIfRouteParameterIsSet();
        },

        async pickwareErpOpenDocumentCreationModalIfRouteParameterIsSet() {
            const documentType = window.history.state.pwErpOrderDocumentCreationTechnicalName;

            if (documentType) {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equals(
                    'technicalName',
                    documentType,
                ));
                const documentTypes = await this.documentTypeRepository.search(criteria, Context.api);

                if (documentTypes.total === 0) {
                    throw new Error(
                        'Could not open the document creation modal, because there was no document type found'
                            + ` for the technical name '${documentType}'`,
                    );
                }

                this.currentDocumentType = documentTypes.first();
                this.onPrepareDocument();
            }
        },

        documentTypeAvailable(documentType) {
            const documentTypeAvailable = this.$super('documentTypeAvailable', documentType);
            if (documentType.technicalName !== DocumentTypeTechnicalNames.INVOICE_CORRECTION) {
                return documentTypeAvailable;
            }

            return this.invoiceExists();
        },

        pickwareErpGetCustomDocumentLabel(document) {
            const createInvoiceRelatedDocumentLabel = (documentNumber, invoiceNumber) => this.$t(
                'sw-order-document-card.pw-erp-invoice-correction.invoice-related-document-label',
                {
                    documentNumber,
                    invoiceNumber,
                },
            );

            const documentTypeTechnicalName = document.documentType.technicalName;
            if (documentTypeTechnicalName === DocumentTypeTechnicalNames.INVOICE_CORRECTION) {
                return createInvoiceRelatedDocumentLabel(
                    get(document, 'config.documentNumber', ''),
                    get(document, 'config.custom.pickwareErpReferencedInvoiceDocumentNumber', ''),
                );
            }
            if (documentTypeTechnicalName === ShopwareDocumentTypeTechnicalName.STORNO) {
                return createInvoiceRelatedDocumentLabel(
                    get(document, 'config.documentNumber', ''),
                    get(document, 'config.custom.invoiceNumber', ''),
                );
            }

            // Original shopware column content. See 'sw-order-document-card'
            // https://github.com/shopware/shopware/blob/df492c3230a21f344e5e956060cd656ed920b153/src/Administration/
            // Resources/app/administration/src/module/sw-order/component/sw-order-document-card/index.js#L130-L133
            return document.config.documentNumber;
        },

        pickwareErpIsInvoiceCorrection(document) {
            return document && document.documentType.technicalName === DocumentTypeTechnicalNames.INVOICE_CORRECTION;
        },

        /**
         * An invoice correction is deletable if it is not referenced by any other document. In other words: it is the
         * newest invoice correction on a given invoice stack.
         */
        pickwareErpIsInvoiceCorrectionDeletable(invoiceCorrection) {
            return !this.documents.find((testedDocument) => {
                const referencedDocumentId = get(testedDocument, 'config.custom.pickwareErpReferencedDocumentId', '');

                return referencedDocumentId === invoiceCorrection.id;
            });
        },

        async pickwareErpDeleteInvoiceCorrection(document) {
            this.documentsLoading = true;
            try {
                await this.repositoryFactory.create('document').delete(document.id, Context.api);

                createSuccessNotification(
                    'sw-order-document-card.pw-erp-invoice-correction.delete-document.notification.success.title',
                    'sw-order-document-card.pw-erp-invoice-correction.delete-document.notification.success.message',
                );
                await this.getList();
            } catch (error) {
                createErrorNotification(
                    'sw-order-document-card.pw-erp-invoice-correction.delete-document.notification.error.title',
                    'sw-order-document-card.pw-erp-invoice-correction.delete-document.notification.error.message',
                );

                throw error;
            } finally {
                this.documentsLoading = false;
            }
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order-document-card": {
            "pw-erp-invoice-correction": {
                "invoice-related-document-label": "{documentNumber} (zu Rechnung {invoiceNumber})",
                "delete-document": {
                    "label": "Löschen",
                    "notification": {
                        "success": {
                            "title": "Erfolg",
                            "message": "Die Rechnungskorrektur wurde gelöscht."
                        },
                        "error": {
                            "title": "Fehler",
                            "message": "Die Rechnungskorrektur konnte nicht gelöscht werden."
                        }
                    }
                }
            }
        }
    },
    "en-GB": {
        "sw-order-document-card": {
            "pw-erp-invoice-correction": {
                "invoice-related-document-label": "{documentNumber} (of invoice {invoiceNumber})",
                "delete-document": {
                    "label": "Delete",
                    "notification": {
                        "success": {
                            "title": "Success",
                            "message": "The invoice correction was deleted."
                        },
                        "error": {
                            "title": "Error",
                            "message": "The invoice correction could not be deleted."
                        }
                    }
                }
            }
        }
    }
}
</i18n>
