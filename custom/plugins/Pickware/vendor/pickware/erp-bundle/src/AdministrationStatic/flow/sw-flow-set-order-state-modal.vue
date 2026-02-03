<template>
    {% block sw_flow_set_order_state_modal_delivery_status %}
    {% parent %}

    <sw-alert
        v-if="showPwErpFlowBuilderWarning"
        variant="warning"
        class="sw-card"
    >
        {{ $t('sw-flow-set-order-state-modal.pw-erp.warning') }}
    </sw-alert>
    {% endblock %}
</template>

<script>
import {
    OrderDeliveryStates,
} from '@pickware-erp-bundle/order/states.js';

export default {
    overrideFrom: 'sw-flow-set-order-state-modal',

    computed: {
        showPwErpFlowBuilderWarning() {
            const showWarning = [
                OrderDeliveryStates.SHIPPED,
                OrderDeliveryStates.RETURNED,
            ];

            return this.config && this.config.order_delivery && showWarning.includes(this.config.order_delivery);
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-flow-set-order-state-modal": {
            "pw-erp": {
                "warning": "Diese Aktion wird nicht durch Pickware ERP unterstützt. Bei entsprechender Statusänderung über den Flow Builder werden keine Bestandsänderungen vorgenommen!"
            }
        }
    },
    "en-GB": {
        "sw-flow-set-order-state-modal": {
            "pw-erp": {
                "warning": "This action is not supported by Pickware ERP. No stock changes are made with the corresponding status change via the Flow Builder!"
            }
        }
    }
}
</i18n>
