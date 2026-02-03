<template>
    {% block sw_order_detail_general_info_card %}
    <div class="sw-order-general-info__order-state">
        <pw-erp-order-delivery-buttons-card
            :order="order"
            :isLoading="isLoading"
        />
    </div>
    {% parent %}
    {% endblock %}

    {% block sw_order_detail_general_line_items_summary_entries %}
    {% parent %}
    <dt ref="returnOrderTotalIdentifier">
        <router-link
            v-if="pwErpShowReturnOrderTab"
            :title="$t('sw-order-detail-general.pickware-erp-starter.open-return-orders')"
            :to="{ name: 'sw.order.detail.pw.erp.returns', params: { id: order.id } }"
        >
            <strong>{{ $t('sw-order-detail-general.pickware-erp-starter.total-including-returns') }}</strong>
        </router-link>
        <template v-else>
            <strong>{{ $t('sw-order-detail-general.pickware-erp-starter.total-including-returns') }}</strong>
        </template>
    </dt>
    <dd ref="returnOrderTotalValue">
        <router-link
            v-if="pwErpShowReturnOrderTab"
            :title="$t('sw-order-detail-general.pickware-erp-starter.open-return-orders')"
            :to="{ name: 'sw.order.detail.pw.erp.returns', params: { id: order.id } }"
        >
            <strong>
                {{ formatWithCurrency(order.price.totalPrice - pwErpTotalReturnOrderValueForInvoiceCorrection,
                                      ...pwErpGetCurrencyFormatterConfigurationWithCurrencyPrecision(currency)
                ) }}
            </strong>
        </router-link>
        <template v-else>
            <strong>
                {{ formatWithCurrency(order.price.totalPrice - pwErpTotalReturnOrderValueForInvoiceCorrection,
                                      ...pwErpGetCurrencyFormatterConfigurationWithCurrencyPrecision(currency)
                ) }}
            </strong>
        </template>
    </dd>
    {% endblock %}
</template>

<script>
import { Component, useFormatter } from '@pickware/shopware-adapter';
import { PickwareFeature } from '@pickware/shopware-administration-feature';
import { getCurrencyFormatterConfigurationWithCurrencyPrecision }
    from '@pickware-erp-bundle/common/currency/currency-formatter-configurations';

import { OrderDetailPageReturnOrderStoreNamespace } from './pw-erp-return-order-tab/return-orders-store.js';

const { mapGetters } = Component.getComponentHelper();
export default {
    overrideFrom: 'sw-order-detail-general',

    setup() {
        const { formatWithCurrency } = useFormatter();

        return {
            formatWithCurrency,
        };
    },

    computed: {
        ...mapGetters(OrderDetailPageReturnOrderStoreNamespace, {
            pwErpTotalReturnOrderValueForInvoiceCorrection: 'getReturnOrdersTotalForInvoiceCorrection',
        }),

        pwErpShowReturnOrderTab() {
            return PickwareFeature.isActive('pickware-erp.feature.return-order-view');
        },
    },

    methods: {
        pwErpGetCurrencyFormatterConfigurationWithCurrencyPrecision:
            getCurrencyFormatterConfigurationWithCurrencyPrecision,
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-order-detail-general": {
            "pickware-erp-starter": {
                "total-including-returns": "Gesamtsumme inkl. Retouren",
                "open-return-orders": "Retouren anzeigen"
            }
        }
    },
    "en-GB": {
        "sw-order-detail-general": {
            "pickware-erp-starter": {
                "total-including-returns": "Total including returns",
                "open-return-orders": "Open return orders"
            }
        }
    }
}
</i18n>
