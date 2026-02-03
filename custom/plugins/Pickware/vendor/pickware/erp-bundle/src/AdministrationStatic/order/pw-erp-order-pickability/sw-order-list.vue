<!-- eslint-disable vue/valid-v-slot !-->
<template>
    {% block sw_order_list_grid_columns %}
    <!-- eslint-disable vue/no-empty-pattern !-->
    <template #[`column-label-pwErpOrderPickabilityStatus`]="{}">
        <sw-icon
            v-tooltip="{ message: $t('sw-order-list.pw-erp.pickability-column.tooltip') }"
            color="#52667A"
            name="pw-erp-icon-picking"
            size="20"
        />
        <span class="pw-erp-hide-column-context-menu" />
    </template>

    <template #[`column-pwErpOrderPickabilityStatus`]="{ item }">
        <pw-erp-order-list-pickability-status-cell
            :warehouseId="pwErpPickabilityWarehouseId"
            :orderPickabilities="pickwareErpLodashGet(item, 'extensions.pickwareErpOrderPickabilities')"
        />
    </template>
    {% parent %}
    {% endblock %}
</template>

<script>
import { Criteria, Service } from '@pickware/shopware-adapter';
import get from 'lodash/get';

import { OrderPickabilityFilterName } from './pw-erp-order-pickability-filter.vue';
import { getDefaultWarehouse } from './warehouse-repository-helper.js';

export default {
    overrideFrom: 'sw-order-list',

    data() {
        return {
            pwErpDefaultWarehouseId: null,
            pwErpOrderPickabilityFilterWarehouseId: null,
        };
    },

    computed: {
        pwErpPickabilityWarehouseId() {
            return this.pwErpOrderPickabilityFilterWarehouseId || this.pwErpDefaultWarehouseId;
        },

        listFilters() {
            const newFilters = this.filterFactory.create('order', {
                [OrderPickabilityFilterName]: {
                    property: 'pickwareErpOrderPickabilities', // No real data-related property value
                    label: '',
                    placeholder: '',
                },
            });
            const filters = this.$super('listFilters');
            newFilters.forEach((newFilter) => filters.unshift(newFilter));

            return filters;
        },

        orderCriteria() {
            const criteria = this.$super('orderCriteria');

            // Add associations for pickability filter
            criteria.addAssociation('pickwareErpOrderPickabilities');

            return criteria;
        },
    },

    methods: {
        pickwareErpLodashGet: get,

        async createdComponent() {
            await this.$super('createdComponent');
            this.pwErpDefaultWarehouseId = (await getDefaultWarehouse()).id;
        },

        /**
         * Use the parent 'getList' function as an event to access the pickability filter (if it is set). Ideally, we
         * would use another function (e.g. 'addQueryScores') where we do not need to wait for another request. But
         * that function does not work due to shopware's component library bug for overrides.
         */
        async getList() {
            await this.$super('getList');

            const reduceSingleFilterToList = (filters, currentFilter) => {
                if (currentFilter.queries) {
                    // Case: multi filter (not, multi, range, ..)
                    return [
                        ...filters,
                        ...reduceFilterList(currentFilter.queries),
                    ];
                }

                // Case: single filter
                return [
                    ...filters,
                    currentFilter,
                ];
            };
            const reduceFilterList = (filters) => filters
                .reduce(
                    (acc, filter) => [
                        ...acc,
                        ...reduceSingleFilterToList([], filter),
                    ],
                    [],
                );

            // See 'pw-erp-order-pickability-filter' for the pickability filter field name. Result can be null.
            const storeCriteria = await Service('filterService').mergeWithStoredFilters(this.storeKey, new Criteria());
            const orderPickabilityFilter = reduceFilterList(storeCriteria.filters ?? [])
                .find((filter) => filter.field === 'pickwareErpOrderPickabilities.warehouseId');
            this.pwErpOrderPickabilityFilterWarehouseId = orderPickabilityFilter ? orderPickabilityFilter.value : null;
        },

        getOrderColumns() {
            const columns = this.$super('getOrderColumns');
            columns.unshift({
                property: 'pwErpOrderPickabilityStatus',
                label: this.$t('sw-order-list.pw-erp.pickability-column.label'),
                allowResize: false,
                sortable: false,
                align: 'center',
                width: '70px',
            });

            return columns;
        },
    },
};
</script>

<style lang="scss">
.sw-order-list__content{
    .pw-erp-hide-column-context-menu + .sw-context-button{
        display: none;
    }
}
</style>

<i18n>
{
    "de-DE": {
        "sw-order-list": {
            "pw-erp": {
                "pickability-column": {
                    "label": "Kommissionierbar",
                    "tooltip": "Zeigt an, ob ausreichend Bestand vorliegt, um eine Bestellung teilweise oder vollständig zu kommissionieren."
                }
            }
        }
    },
    "en-GB": {
        "sw-order-list": {
            "pw-erp": {
                "pickability-column": {
                    "label": "Pickable",
                    "tooltip": "Indicates whether there is sufficient stock to partially or completely pick an order."
                }
            }
        }
    }
}
</i18n>
