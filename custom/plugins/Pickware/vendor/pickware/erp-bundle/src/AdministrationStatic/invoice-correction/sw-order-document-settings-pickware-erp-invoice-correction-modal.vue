<template>
    <!-- Do not allow the user to upload an own document file -->
    {% block sw_order_document_settings_modal_form_file_upload %}
    {% endblock %}

    {% block sw_order_document_settings_modal_form_document_comment %}
    {% parent %}

    <sw-alert variant="info">
        {{ $t('sw-order-document-settings.pw-erp-invoice-correction-modal.considered-changes') }}
    </sw-alert>
    {% endblock %}
</template>

<script>
import { Service } from '@pickware/shopware-adapter';

export default {
    name: 'sw-order-document-settings-pickware-erp-invoice-correction-modal',
    extendsFrom: 'sw-order-document-settings-modal',

    methods: {
        async onCreateDocument(additionalAction = false) {
            if (!await this.validateInvoiceCorrectionCreation()) {
                return;
            }

            this.$super('onCreateDocument', additionalAction);
        },

        async onPreview() {
            if (!await this.validateInvoiceCorrectionCreation()) {
                return;
            }

            this.$super('onPreview');
        },

        async validateInvoiceCorrectionCreation() {
            const invoiceCorrectionApiService = Service('pickwareErpInvoiceCorrectionApiService');
            const documentApiService = Service('documentService');

            try {
                await invoiceCorrectionApiService.checkValid({
                    orderId: this.order.id,
                });
            } catch (error) {
                if (error.response?.data?.errors) {
                    documentApiService.$listener(
                        documentApiService.createDocumentEvent(
                            'create-document-fail',
                            error.response.data.errors.pop(),
                        ),
                    );

                    return false;
                }
            }

            return true;
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order-document-settings": {
            "pw-erp-invoice-correction-modal": {
              "considered-changes": "Die Rechnungskorrektur enthält alle Retouren und weitere Änderungen an der Bestellung, die seit der letzten Rechnungsstellung vorgenommen wurden."
            }
        }
    },
    "en-GB": {
        "sw-order-document-settings": {
            "pw-erp-invoice-correction-modal": {
              "considered-changes": "The invoice correction contains all return orders and other changes of the order that have been made since the last invoice creation."
            }
        }
    }
}
</i18n>
