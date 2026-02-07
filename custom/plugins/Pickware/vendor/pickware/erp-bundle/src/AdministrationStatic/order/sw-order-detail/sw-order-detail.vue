<template>
    <!-- Use the block sw_order_detail_content_tabs_documents here instead of sw_order_detail_content_tabs_extension
        because SwagCommerical overwrites the block sw_order_detail_content_tabs_extension without calling the parent
        resulting in our tab items missing when SwagCommerical is active.
        If this is fixed in SwagCommerical we want to go back to sw_order_detail_content_tabs_extension.
        Further information: https://github.com/pickware/shopware-plugins/issues/4288 -->
    {% block sw_order_detail_content_tabs_documents %}
    {% parent %}
    <sw-tabs-item
        v-if="pwErpShowReturnOrderTab"
        class="sw-order-detail__tabs-tab-returns"
        :class="{ 'has-warning': isOrderEditing }"
        :title="$t('sw-order-detail.pickware-erp-starter.returns')"
        :route="{ name: 'sw.order.detail.pw.erp.returns', params: { id: $route.params.id } }"
    >
        {{ $t('sw-order-detail.pickware-erp-starter.returns') }} ({{ returnOrders.length }})
    </sw-tabs-item>
    {% endblock %}
</template>

<script>
import {Component, Service, State} from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';
import { PickwareErpStarterGlobalEventBusServiceName } from '@pickware-erp-bundle/common/event-bus';
import { InvoiceCorrectionEvents } from '@pickware-erp-bundle/invoice-correction/invoice-correction-constants';
import {
    OrderDetailPageReturnOrderStore,
    OrderDetailPageReturnOrderStoreNamespace,
} from '@pickware-erp-bundle/order/sw-order-detail/pw-erp-return-order-tab/return-orders-store';
import { StockFlowNamespace, StockFlowStore } from '@pickware-erp-bundle/stock-flow/stock-flow-store';

import { OrderDetailPageEvents } from './events.js';
import { OrderDetailPageStore, OrderDetailPageStoreNamespace } from './order-detail-page-store.js';

const { mapActions, mapState } = Component.getComponentHelper();

export default {
    overrideFrom: 'sw-order-detail',

    computed: {
        ...mapState(OrderDetailPageReturnOrderStoreNamespace, ['returnOrders']),

        orderCriteria() {
            const criteria = this.$super('orderCriteria');

            // Add associations for stocks
            criteria.addAssociation('pickwareErpStocks');

            return criteria;
        },

        pwErpShowReturnOrderTab() {
            return PickwareFeature.isActive('pickware-erp.feature.return-order-view');
        },
    },

    beforeCreate() {
        State.registerModule(OrderDetailPageStoreNamespace, OrderDetailPageStore);
        State.registerModule(StockFlowNamespace, StockFlowStore);
        State.registerModule(OrderDetailPageReturnOrderStoreNamespace, OrderDetailPageReturnOrderStore);
    },

    beforeUnmount() {
        State.unregisterModule(OrderDetailPageStoreNamespace);
        State.unregisterModule(StockFlowNamespace);
        State.unregisterModule(OrderDetailPageReturnOrderStoreNamespace);

        Service(PickwareErpStarterGlobalEventBusServiceName).$off(OrderDetailPageEvents.reloadOrderState);
    },

    mounted() {
        // onSaveEdits() is called by the original component when the order status is changed in any way. So we need
        // to call it as well, to reload the entity data()
        Service(PickwareErpStarterGlobalEventBusServiceName).$on(OrderDetailPageEvents.reloadOrderState, this.onSaveEdits);
    },

    methods: {
        ...mapActions(OrderDetailPageStoreNamespace, { pickwareErpSetIsEditing: 'setIsEditing' }),
        ...mapActions(StockFlowNamespace, {
            pickwareErpFetchStockFlow: 'fetchStockFlow',
            pickwareErpFetchCombinedReturnOrderStockFlowForOrder: 'fetchCombinedReturnOrderStockFlowForOrder',
        }),

        ...mapActions(OrderDetailPageReturnOrderStoreNamespace, {
            pickwareErpFetchReturnOrders: 'fetchReturnOrders',
        }),

        createdComponent() {
            this.$super('createdComponent');
            this.pickwareErpFetchStockFlow({ order: { id: this.orderId } });
            this.pickwareErpFetchReturnOrders(this.orderId);
            this.pickwareErpFetchCombinedReturnOrderStockFlowForOrder({ orderId: this.orderId });
        },

        onSaveEdits() {
            this.$super('onSaveEdits');
            Service(PickwareErpStarterGlobalEventBusServiceName)
                .$emit(InvoiceCorrectionEvents.REVALIDATE_INVOICE_CORRECTION_CREATION_BUTTON);
            this.pickwareErpFetchStockFlow({ order: { id: this.orderId } });
            this.pickwareErpFetchCombinedReturnOrderStockFlowForOrder({ orderId: this.orderId });
            this.pickwareErpFetchReturnOrders(this.orderId);
        },

        saveEditsFinish() {
            this.$super('saveEditsFinish');
            this.pickwareErpSetIsEditing(false);
        },

        onUpdateEditing(newValue) {
            this.$super('onUpdateEditing', newValue);
            this.pickwareErpSetIsEditing(newValue);
        },
    },
};
</script>
<i18n>
{
    "de-DE": {
        "sw-order-detail": {
            "pickware-erp-starter": {
                "returns": "Retouren"
            }
        }
    },
    "en-GB": {
        "sw-order-detail": {
            "pickware-erp-starter": {
                "returns": "Returns"
            }
        }
    }
}
</i18n>
