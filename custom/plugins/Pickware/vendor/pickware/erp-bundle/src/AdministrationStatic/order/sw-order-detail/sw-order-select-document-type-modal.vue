<template>
    {% block sw_order_select_document_type_modal_document_types %}
    <!-- Overwrite the sw-radio-field from shop to overwrite the options with our own extendes documentTypes -->
    <sw-radio-field
        v-if="!isLoading && documentTypes.length"
        v-model:value="documentType"
        :options="pwErpDocumentTypes"
        class="sw-order-select-document-type-modal__radio-field"
        @update:value="onRadioFieldChange"
    />
    {% endblock %}
</template>

<script>
import { Criteria } from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';
import { DocumentTypeTechnicalNames } from '@pickware-erp-bundle/document/document-types.js';
import { hasOrderInvoiceWithoutStorno }
    from '@pickware-erp-bundle/invoice-correction/has-order-invoice-without-storno';

export default {
    overrideFrom: 'sw-order-select-document-type-modal',

    data() {
        return {
            pwErpHasInvoicesWithoutStornoDocuments: false,
        };
    },

    computed: {
        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            const notFilter = [Criteria.equals('technicalName', DocumentTypeTechnicalNames.SUPPLIER_ORDER)];
            if (!PickwareFeature.isActive('pickware-erp.feature.invoice-correction')) {
                notFilter.push(Criteria.equals('technicalName', DocumentTypeTechnicalNames.INVOICE_CORRECTION));
            }
            criteria.addFilter(Criteria.not('OR', notFilter));

            return criteria;
        },

        pwErpDocumentTypes() {
            const invoiceCorrectionDocumentType = this.documentTypeCollection.filter(
                (documentType) => documentType.technicalName === DocumentTypeTechnicalNames.INVOICE_CORRECTION,
            ).first();

            return this.documentTypes.map((documentType) => {
                if (invoiceCorrectionDocumentType && invoiceCorrectionDocumentType.id === documentType.value) {
                    documentType.disabled = !this.pwErpHasInvoicesWithoutStornoDocuments;

                    return this.addHelpTextToOption(documentType, {
                        technicalName: DocumentTypeTechnicalNames.INVOICE_CORRECTION,
                    });
                }

                return documentType;
            });
        },
    },

    methods: {
        hasOrderInvoiceWithoutStorno,

        createdComponent() {
            this.$super('createdComponent');

            this.pwErpValidateIfInvoiceCorrectionCanBeCreated();
        },

        async pwErpValidateIfInvoiceCorrectionCanBeCreated() {
            this.pwErpHasInvoicesWithoutStornoDocuments = await this.hasOrderInvoiceWithoutStorno(this.order.id);
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order": {
            "components": {
                "selectDocumentTypeModal": {
                    "helpText": {
                        "pickware_erp_invoice_correction": "Eine Rechnungskorrektur kann nur erstellt werden wenn mindestens eine Rechnung ohne Stornorechnung existiert."
                    }
                }
            }
        }
    },
    "en-GB": {
        "sw-order": {
            "components": {
                "selectDocumentTypeModal": {
                    "helpText": {
                        "pickware_erp_invoice_correction": "An invoice correction can only be created if there is at least one invoice without a cancellation invoice."
                    }
                }
            }
        }
    }
}
</i18n>
