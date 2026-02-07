<template>
    {% block sw_order_document_card_grid_column_date %}
    {% parent %}

    <!-- Because v-slot directives must be owned by a custom component (which in this case would be the 'sw-data-grid'
         of the 'sw-order-document-card' component) we need to eslint-ignore the next line. -->
    <!-- eslint-disable-next-line vue/valid-v-slot -->
    <template #[`column-documentType.name`]="{ item }">
        <p
            v-tooltip="$t('sw-order-document-card.pw-erp-picklist.show-document')"
            class="document-type-description link"
            @click="onOpenDocument(item.id, item.deepLinkCode)"
        >
            {{ pickwareErpGetDocumentTypeName(item) }}
        </p>
    </template>
    {% endblock %}

    {% block sw_order_document_card_grid_action_download %}
    {% parent %}

    <sw-context-menu-item
        v-if="pickwareErpIsPicklistDocument(item)"
        variant="danger"
        @click="pickwareErpDeletePicklist(item)"
    >
        {{ $t('sw-order-document-card.pw-erp-picklist.delete-document.label') }}
    </sw-context-menu-item>
    {% endblock %}
</template>

<script>
import { Context, Criteria } from '@pickware/shopware-adapter';
import { createErrorNotification, createSuccessNotification } from '@pickware/shopware-administration-notification';
import { formatWarehouseLabel } from '@pickware/shopware-entity-label-formatter';

import { PicklistDocumentTypeTechnicalName } from './picklist-document-type.js';

export default {
    overrideFrom: 'sw-order-document-card',

    inject: [
        'repositoryFactory',
    ],

    data() {
        return {
            pickwareErpWarehouses: [],
        };
    },

    methods: {
        async getList() {
            await this.$super('getList');

            this.documentsLoading = true;
            try {
                this.pickwareErpWarehouses = await this
                    .repositoryFactory.create('pickware_erp_warehouse')
                    .search(new Criteria(1, 500), Context.api);
            } finally {
                this.documentsLoading = false;
            }
        },

        pickwareErpGetDocumentTypeName(document) {
            if (!this.pickwareErpIsPicklistDocument(document)) {
                return document.documentType.name;
            }

            const warehouse = this.pickwareErpWarehouses.find(
                (warehouse) => warehouse.id === document.config.warehouseId,
            );
            if (!warehouse) {
                return document.documentType.name;
            }

            return `${document.documentType.name}, ${formatWarehouseLabel(warehouse)}`;
        },

        pickwareErpIsPicklistDocument(document) {
            return document && document.documentType.technicalName === PicklistDocumentTypeTechnicalName;
        },

        async pickwareErpDeletePicklist(document) {
            this.documentsLoading = true;
            try {
                await this.repositoryFactory.create('document').delete(document.id, Context.api);

                createSuccessNotification(
                    'sw-order-document-card.pw-erp-picklist.delete-document.notification.success.title',
                    'sw-order-document-card.pw-erp-picklist.delete-document.notification.success.message',
                );
                await this.getList();
            } catch (error) {
                createErrorNotification(
                    'sw-order-document-card.pw-erp-picklist.delete-document.notification.error.title',
                    'sw-order-document-card.pw-erp-picklist.delete-document.notification.error.message',
                );

                throw error;
            } finally {
                this.documentsLoading = false;
            }
        },
    },
};
</script>

<style lang="scss">
.sw-order-document-card {
    .document-type-description:hover {
        cursor: pointer;
        text-decoration: underline;
    }
}
</style>

<i18n>
{
    "de-DE": {
        "sw-order-document-card": {
            "pw-erp-picklist": {
                "show-document": "Dokument anzeigen",
                "delete-document": {
                    "label": "Löschen",
                    "notification": {
                        "success": {
                            "title": "Erfolg",
                            "message": "Die Pickliste wurde gelöscht."
                        },
                        "error": {
                            "title": "Fehler",
                            "message": "Die Pickliste konnte nicht gelöscht werden."
                        }
                    }
                }
            }
        }
    },
    "en-GB": {
        "sw-order-document-card": {
            "pw-erp-picklist": {
                "show-document": "Show document",
                "delete-document": {
                    "label": "Delete",
                    "notification": {
                        "success": {
                            "title": "Success",
                            "message": "The picklist was deleted."
                        },
                        "error": {
                            "title": "Error",
                            "message": "The picklist could not be deleted."
                        }
                    }
                }
            }
        }
    }
}
</i18n>
