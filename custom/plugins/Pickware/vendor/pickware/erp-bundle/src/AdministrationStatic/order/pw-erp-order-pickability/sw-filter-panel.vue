<!-- eslint-disable vue/valid-v-slot !-->
<template>
    {% block sw_filter_panel_extension_point %}
    {% parent %}

    <!-- eslint-disable vue/valid-v-else-if !-->
    <pw-erp-order-pickability-filter
        v-else-if="filter.name === OrderPickabilityFilterName"
        :filter="filter"
        @filter-update="updateFilter"
        @filter-reset="resetFilter"
    />
    {% endblock %}
</template>

<script>
import { OrderPickabilityFilterName } from './pw-erp-order-pickability-filter.vue';

export default {
    overrideFrom: 'sw-filter-panel',

    data() {
        return {
            OrderPickabilityFilterName,
        };
    },
};
</script>
