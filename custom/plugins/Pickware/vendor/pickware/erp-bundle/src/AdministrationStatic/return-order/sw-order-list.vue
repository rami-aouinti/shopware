<template>
    {% block sw_order_list_grid_columns_actions_delete %}
    <sw-context-menu-item
        v-if="showCreateReturnOrderButton"
        ref="createNewReturnAction"
        class="sw-order-list__create-return-view-action"
        :routerLink="{ name: 'pw.erp.return.order.create', params: { id: item.id } }"
        :disabled="pwErpReturnOrderCreationDenyList.includes(item.id)"
    >
        {{ $t('pickware-erp-starter-sw-order-list.create-return-order.label') }}
    </sw-context-menu-item>
    {% parent %}
    {% endblock %}
</template>

<script>
import { PickwareFeature } from '@pickware/shopware-administration-feature';

export default {
    overrideFrom: 'sw-order-list',
    name: 'sw-order-list',

    data() {
        return {
            pwErpReturnOrderCreationDenyList: [], // order ids
        };
    },

    computed: {
        showCreateReturnOrderButton() {
            return PickwareFeature.isActive('pickware-erp.feature.return-order-management');
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "pickware-erp-starter-sw-order-list": {
            "create-return-order": {
                "label": "Retoure erfassen"
            }
        }
    },
    "en-GB": {
        "pickware-erp-starter-sw-order-list": {
            "create-return-order": {
                "label": "Create return"
            }
        }
    }
}
</i18n>
