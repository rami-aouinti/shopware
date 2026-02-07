<template>
    <!-- Inject a warehouse-select when the action should generate a picklist -->
    {% block sw_flow_generate_document_modal_document_type_multiple %}
    {% parent %}

    <pw-erp-warehouse-select
        v-model:value="pickwareErpWarehouse"
        class="pw-erp-warehouse-select"
        :disabled="!pickwareErpUseWarehouseSelect"
        :label="$t('sw-flow-generate-document-modal.pw-erp.warehouse-selection-label')"
    />
    {% endblock %}
</template>

<script>
import { ShopwareError } from '@pickware/shopware-adapter';
import { PicklistDocumentTypeTechnicalName } from '@pickware-erp-bundle/picklist/picklist-document-type';

export default {
    overrideFrom: 'sw-flow-generate-document-modal',

    data() {
        return {
            pickwareErpWarehouse: null,
        };
    },

    computed: {
        pickwareErpUseWarehouseSelect() {
            return this.documentTypesSelected.includes(PicklistDocumentTypeTechnicalName);
        },

        documentTypeCriteria() {
            const criteria = this.$super('documentTypeCriteria');

            return criteria;
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            if (!this.documentTypesSelected.includes(PicklistDocumentTypeTechnicalName)) {
                return;
            }

            // The following code, which determines the initially selected document types, is a copy of the original
            // 'sw-flow-generate-document-modal' code
            let initiallySelectedDocumentTypes = [];
            if (this.sequence?.config?.documentType) {
                initiallySelectedDocumentTypes = [this.sequence.config];
            } else {
                initiallySelectedDocumentTypes = this.sequence?.config?.documentTypes || [];
            }

            const initiallySelectedDocumentTypePicklist = initiallySelectedDocumentTypes
                .filter((type) => type.documentType === PicklistDocumentTypeTechnicalName)
                .shift();

            if (initiallySelectedDocumentTypePicklist?.warehouseId) {
                this.pickwareErpWarehouse = {
                    id: initiallySelectedDocumentTypePicklist.warehouseId,
                };
            }
        },

        onAddAction() {
            if (!this.pickwareErpUseWarehouseSelect) {
                this.$super('onAddAction');

                return;
            }
            // The following code is a copy of the original 'sw-flow-generate-document-modal' with the additional
            // 'warehouseId' in the sequence config (of each document).
            if (!this.documentTypesSelected.length) {
                this.fieldError = new ShopwareError({
                    code: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                });

                return;
            }

            let sequence = {
                ...this.sequence,
            };

            const documentTypes = this.documentTypesSelected.map((documentType) => {
                const result = {
                    documentType,
                    documentRangerType: `document_${documentType}`,
                    config: {},
                };

                if (documentType === PicklistDocumentTypeTechnicalName) {
                    result.config.warehouseId = this.pickwareErpWarehouse.id;
                }

                return result;
            });

            sequence = {
                ...sequence,
                config: { documentTypes },
            };

            this.$emit('process-finish', sequence);
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-flow-generate-document-modal": {
            "pw-erp": {
                "warehouse-selection-label": "Lager (für Pickliste)"
            }
        }
    },
    "en-GB": {
        "sw-flow-generate-document-modal": {
            "pw-erp": {
                "warehouse-selection-label": "Warehouse (for picklist)"
            }
        }
    }
}
</i18n>
