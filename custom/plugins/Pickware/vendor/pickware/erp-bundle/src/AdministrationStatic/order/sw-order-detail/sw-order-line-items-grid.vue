<!-- eslint-disable vue/valid-v-slot !-->
<template>
    {% block sw_order_line_items_grid_grid_columns %}
    {% parent %}

    <template #[`column-pickwareErpStarterShipped`]="{ item }">
        <template v-if="item.type === pwErpOrderLineItemTypes.PRODUCT">
            {{ pwErpGetLineItemShipped(item.productId) }}
        </template>
    </template>

    <template #[`column-pickwareErpStarterReturned`]="{ item }">
        <template v-if="item.type === pwErpOrderLineItemTypes.PRODUCT">
            {{ pwErpGetLineItemReturned(item.productId) }}
        </template>
    </template>
    {% endblock %}
</template>

<script>
import { Component } from '@pickware/shopware-adapter';
import { StockFlowNamespace } from '@pickware-erp-bundle/stock-flow/stock-flow-store.js';
import get from 'lodash/get';

import { OrderLineItemTypes } from '../order-line-item-types.js';
import {
    OrderDetailPageReturnOrderStoreNamespace,
} from './pw-erp-return-order-tab/return-orders-store.js';

const { mapState, mapGetters } = Component.getComponentHelper();

export default {
    overrideFrom: 'sw-order-line-items-grid',

    data() {
        return {
            pwErpOrderLineItemTypes: OrderLineItemTypes,
        };
    },

    computed: {
        ...mapState(StockFlowNamespace, {
            pickwareErpStockFlow: 'stockFlow',
        }),

        ...mapGetters(OrderDetailPageReturnOrderStoreNamespace, {
            pickwareErpGetReturnOrderLineItemQuantity: 'getReturnOrderLineItemQuantity',
        }),

        getLineItemColumns() {
            const columns = this.$super('getLineItemColumns');

            // Try to place our columns after the 'quantity' column
            const quantityColumnIndex = columns.findIndex((column) => column.property === 'quantity');
            const addColumnsAtIndex = (quantityColumnIndex !== -1) ? quantityColumnIndex + 1 : 0;

            columns.splice(
                addColumnsAtIndex,
                0,
                {
                    property: 'pickwareErpStarterShipped',
                    label: this.$t('sw-order-line-items-grid.pickware-erp-starter.shipped'),
                    allowResize: false,
                    align: 'right',
                    width: '90px',
                },
                {
                    property: 'pickwareErpStarterReturned',
                    label: this.$t('sw-order-line-items-grid.pickware-erp-starter.returned'),
                    allowResize: false,
                    align: 'right',
                    width: '90px',
                },
            );

            return columns;
        },
    },

    methods: {
        // "shipped" is stock flow into the order
        pwErpGetLineItemShipped(productId) {
            return get(this.pickwareErpStockFlow, `${productId}.incoming`, 0);
        },

        // "returned" is the aggregated quantity of all return order line items in the relevant state.
        // Legacy: before the return order feature in ERP, the "returned" value was the outgoing stock of the order
        // itself. We did not migrate old returned orders into return orders. In order to keep the value correct for
        // both old and newer orders, we show whatever value is higher.
        pwErpGetLineItemReturned(productId) {
            const legacyReturnedQuantity = get(this.pickwareErpStockFlow, `${productId}.outgoing`, 0);

            return Math.max(legacyReturnedQuantity, this.pickwareErpGetReturnOrderLineItemQuantity(productId));
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order-line-items-grid": {
            "pickware-erp-starter": {
                "shipped": "Versendet",
                "returned": "Retourniert"
            }
        }
    },
    "en-GB": {
        "sw-order-line-items-grid": {
            "pickware-erp-starter": {
                "shipped": "Shipped",
                "returned": "Returned"
            }
        }
    }
}
</i18n>

