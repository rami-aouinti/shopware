<template>
    {% block sw_bulk_edit_order_content_order_status_card %}

    <pw-erp-bulk-edit-order-delivery-buttons-card
        :orderIds="selectedIds"
        :isLoading="isLoading"
    />
    {% parent %}
    {% endblock %}
</template>

<script>
import { Component, State } from '@pickware/shopware-adapter';
import {
    BulkEditDocumentStore,
    BulkEditDocumentStoreNamespace,
} from '@pickware-erp-bundle/order/bulk-edit/bulk-edit-document-store';

import {
    BulkEditDeliveryStore,
    BulkEditDeliveryStoreNamespace,
} from './pw-erp-order-bulk-edit-action-buttons/bulk-edit-delivery-store.js';

const { mapGetters, mapMutations } = Component.getComponentHelper();

export default {
    overrideFrom: 'sw-bulk-edit-order',

    computed: {
        ...mapGetters(BulkEditDocumentStoreNamespace, {
            pwErpDocumentTypes: 'getDocumentTypes',
        }),
    },

    beforeCreate() {
        State.registerModule(BulkEditDeliveryStoreNamespace, BulkEditDeliveryStore);
        State.registerModule(BulkEditDocumentStoreNamespace, BulkEditDocumentStore);
    },

    beforeUnmount() {
        State.unregisterModule(BulkEditDeliveryStoreNamespace);
        State.unregisterModule(BulkEditDocumentStoreNamespace);
    },

    methods: {
        ...mapMutations(BulkEditDocumentStoreNamespace, {
            pwErpSetDocumentIsChanged: 'setOrderDocumentsIsChanged',
        }),

        onChangeDocument(type, isChanged) {
            if (this.pwErpDocumentTypes.includes(type)) {
                this.pwErpSetDocumentIsChanged({
                    type,
                    isChanged,
                });

                return;
            }

            this.$super('onChangeDocument', type, isChanged);
        },
    },
};
</script>
