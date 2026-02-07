<!-- eslint-disable vue/valid-v-slot !-->
<template>
    {% block sw_product_list_grid_columns %}
    {% parent %}
    <template #column-availableStock="{ item }">
        <div v-if="!pwErpProductIsStockManaged(item)">
            -
        </div>
    </template>
    {% endblock %}

    {% block sw_product_list_grid_columns_stock %}
    <template #column-stock="{ item }">
        <div v-if="!pwErpProductIsStockManaged(item)">
            -
        </div>
    </template>
    {% endblock %}
</template>

<script>
export default {
    overrideFrom: 'sw-product-list',

    computed: {
        productCriteria() {
            const criteria = this.$super('productCriteria');
            criteria.addAssociation('pickwareErpPickwareProduct');

            return criteria;
        },
    },

    methods: {
        pwErpProductIsStockManaged(productEntity) {
            return productEntity.extensions.pickwareErpPickwareProduct
                    && !productEntity.extensions.pickwareErpPickwareProduct.isStockManagementDisabled;
        },
    },
};
</script>
