<template>
    <!-- Remove the delivery state from the state history card since we add a custom version to the delivery metadata
         card instead. -->
    {% block sw_order_state_history_card_delivery %}
    {% endblock %}

    <!-- Extend the order status select to show a confirmation modal
    when the state should be changed to 'complete', but the shipping state is not already set to 'shipped' -->
    {% block sw_order_state_history_card_order %}
    {% parent %}

    <pw-erp-confirmation-modal
        v-if="pwErpOrderStateConfirmationModal"
        :title="$t('sw-order-state-history-card.order-state-confirmation-modal.title')"
        @close="pwErpCancelOrderStatusChange"
        @confirm="pwErpConfirmOrderStatusChange"
    >
        {{ $t('sw-order-state-history-card.order-state-confirmation-modal.message') }}
    </pw-erp-confirmation-modal>
    {% endblock %}
</template>

<script>
import { OrderDeliveryStates, OrderStateTransitions } from '../states.js';

export default {
    overrideFrom: 'sw-order-state-history-card',

    data() {
        return {
            pwErpOrderStateConfirmationModal: false,
        };
    },

    methods: {
        onOrderStateSelected(actionName) {
            // Cancel the parent onOrderStateSelected() method when selected status is 'complete'
            // and the primary delivery is not 'shipped' yet
            if (actionName === OrderStateTransitions.COMPLETE
                && this.delivery
                && this.delivery.stateMachineState.technicalName !== OrderDeliveryStates.SHIPPED) {
                this.pwErpOrderStateConfirmationModal = true;

                return;
            }

            this.$super('onOrderStateSelected', actionName);
        },

        pwErpConfirmOrderStatusChange() {
            this.pwErpOrderStateConfirmationModal = false;
            this.$super('onOrderStateSelected', OrderStateTransitions.COMPLETE);
        },

        async pwErpCancelOrderStatusChange() {
            this.pwErpOrderStateConfirmationModal = false;
            await this.$nextTick();
            // Mimic a state change so the parent component reloads the order which in turn resets the view
            // and the current (aborted) state change selection
            this.$emit('order-state-change');
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order-state-history-card": {
            "order-state-confirmation-modal": {
                "title": "Hinweis",
                "message": "Der Lieferstatus der Bestellung steht aktuell nicht auf \"Versandt\" und somit wurde ggf. noch kein Bestand für diese ausgebucht. Soll der Bestellstatus trotzdem auf \"Abgeschlossen\" aktualisiert werden?"
            }
        }
    },
    "en-GB": {
        "sw-order-state-history-card": {
            "order-state-confirmation-modal": {
                "title": "Hint",
                "message": "The delivery status of the order is not \"Shipped\" and the stock may have not been cleared. Should the order status be updated to \"Done\" anyway?"
            }
        }
    }
}
</i18n>

