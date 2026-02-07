<template>
    {% block sw_order_detail_base_general_info_order_states_order %}
    {% parent %}

    <pw-erp-modal-promise-adapter
        ref="confirmationModal"
        #default="{ visible, confirm, cancel }"
    >
        <pw-erp-confirmation-modal
            v-if="visible"
            :title="$t('pickware-erp-starter.order-state-change-modal.title')"
            @close="cancel"
            @confirm="confirm"
        >
            {{ $t('pickware-erp-starter.order-state-change-modal.description') }}
        </pw-erp-confirmation-modal>
    </pw-erp-modal-promise-adapter>

    {% endblock %}
</template>

<script>
import { Component } from '@pickware/shopware-adapter';
import { createWarningNotification } from '@pickware/shopware-administration-notification/src/notifications';
import { StockFlowNamespace } from '@pickware-erp-bundle/stock-flow/stock-flow-store.js';
import get from 'lodash/get';

import { OrderLineItemTypes } from '../order-line-item-types.js';

const { mapState } = Component.getComponentHelper();

export default {
    overrideFrom: 'sw-order-general-info',

    data() {
        return {
            pwErpShowDeliveryStateChangeConfirmationModal: false,
        };
    },

    computed: {
        ...mapState(StockFlowNamespace, {
            pickwareErpStockFlow: 'stockFlow',
        }),
    },

    methods: {
        pwErpGetLineItemShipped(productId) {
            return get(this.pickwareErpStockFlow, `${productId}.incoming`, 0);
        },

        async onStateSelected(stateType, actionName) {
            // Open confirmation modal and wait for resolve or reject
            if (actionName === 'ship' && !this.pwErpAreAllProductOrderLineItemsShipped()) {
                try {
                    await this.$refs.confirmationModal.show();
                } catch (error) {
                    createWarningNotification(
                        this.$t('pickware-erp-starter.order-state-change-notification.title'),
                        this.$t('pickware-erp-starter.order-state-change-notification.description'),
                    );
                    // Emit event to reset state
                    this.$emit('state-select-cancelled');

                    return;
                }
            }

            this.$super('onStateSelected', stateType, actionName);
        },

        pwErpAreAllProductOrderLineItemsShipped() {
            return this.order.lineItems
                .filter((lineItem) => lineItem.type === OrderLineItemTypes.PRODUCT)
                .every((lineItem) => {
                    const shipped = this.pwErpGetLineItemShipped(lineItem.productId);

                    return shipped >= lineItem.quantity;
                });
        },

    },
};
</script>

<i18n>
{
    "en-GB": {
        "pickware-erp-starter": {
            "order-state-change-modal": {
                "title": "Warning",
                "description": "Not all positions of the order have been shipped yet. If they should still be shipped, use the corresponding button to ship the order. Should the change of the delivery status still be carried out?"
            },
            "order-state-change-notification": {
                "title": "Cancelled",
                "description": "The order state hasn't been changed."
            }
        }

    },
    "de-DE": {
        "pickware-erp-starter": {
            "order-state-change-modal": {
                "title": "Hinweis",
                "description": "Es wurden noch nicht alle Positionen der Bestellung versendet. Wenn diese noch versendet werden sollen, benutze den entsprechenden Button zum Versenden der Bestellung. Soll die Lieferstatusänderung dennoch durchgeführt werden?"
            },
            "order-state-change-notification": {
                "title": "Abgebrochen",
                "description": "Der Bestellstatus wurde nicht geändert."
            }
        }

    }
}
</i18n>
