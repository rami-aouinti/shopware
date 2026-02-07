<template>
    <!-- Do not show the document number (and date field) here -->
    {% block sw_order_document_settings_modal_form_first_row %}
    {% endblock %}

    <!-- Do not allow the user to upload an own document file -->
    {% block sw_order_document_settings_modal_form_file_upload %}
    {% endblock %}

    {% block sw_order_document_settings_modal_form_additional_content %}
    <sw-container
        columns="1fr 1fr"
        gap="0px 10px"
    >
        <sw-datepicker
            v-model:value="documentConfig.documentDate"
            required
            :label="$t('sw-order.documentModal.labelDocumentDate')"
        />

        <pw-erp-warehouse-select v-model:value="warehouse" />
    </sw-container>
    {% endblock %}

    <!-- Overwrite the comment field to set a different label -->
    {% block sw_order_document_settings_modal_form_document_comment %}
    <sw-text-field
        v-model:value="documentConfig.documentComment"
        :label="$t('sw-order-document-settings-pickware-erp-picklist-modal.document-comment')"
    />
    {% endblock %}
</template>

<script>
import { createErrorNotification } from '@pickware/shopware-administration-notification';
import get from 'lodash.get';

export default {
    name: 'sw-order-document-settings-pickware-erp-picklist-modal',
    extendsFrom: 'sw-order-document-settings-modal',

    data() {
        return {
            warehouse: null,
        };
    },

    watch: {
        warehouse(warehouse) {
            if (warehouse) {
                this.documentConfig.warehouseId = warehouse.id;
            }
        },
    },

    methods: {
        onPreview() {
            if (!this.warehouse) {
                createErrorNotification(
                    'sw-order-document-settings-pickware-erp-picklist-modal.error',
                    'sw-order-document-settings-pickware-erp-picklist-modal.no-warehouse-selected',
                );

                return;
            }

            this.$super('onPreview');
        },

        onCreateDocument(additionalAction = false) {
            if (!this.warehouse) {
                createErrorNotification(
                    'sw-order-document-settings-pickware-erp-picklist-modal.error',
                    'sw-order-document-settings-pickware-erp-picklist-modal.no-warehouse-selected',
                );

                return;
            }

            this.$super('onCreateDocument', additionalAction);
        },

        async createdComponent() {
            await this.$super('createdComponent');

            this.documentConfig.documentComment = get(
                this.order,
                'customFields.pickware_erp_starter_picking_instruction',
                '',
            );
        },

    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order-document-settings-pickware-erp-picklist-modal": {
            "error": "Fehler",
            "document-comment": "Pick-Anweisung",
            "no-warehouse-selected": "Wähle zunächst das Lager aus, aus dem kommissioniert werden soll."
        }
    },
    "en-GB": {
        "sw-order-document-settings-pickware-erp-picklist-modal": {
            "error": "Error",
            "document-comment": "Picking instructions",
            "no-warehouse-selected": "Select the warehouse the order should be picked in."
        }
    }
}
</i18n>
