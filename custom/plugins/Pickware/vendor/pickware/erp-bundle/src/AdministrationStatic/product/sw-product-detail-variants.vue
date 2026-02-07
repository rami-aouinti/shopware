<script>
import { Context, Criteria, Service } from '@pickware/shopware-adapter';
import merge from 'lodash/merge';

export default {
    overrideFrom: 'sw-product-detail-variants',

    computed: {
        pickwareProductRepository() {
            return Service('repositoryFactory')
                .create('pickware_erp_pickware_product');
        },
    },

    methods: {

        async updateVariations() {
            await this.$super('updateVariations');
            // We need to step in, when all variants have been created. When the modal gets closed by finishing the
            // creation process 'updateVariations()' gets called. So we have to override this method by fetching our
            // pickware product of the new variants and overwrite the 'isStockManagementDisabled' property.
            await this.savePickwareProduct();
        },

        async savePickwareProduct() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.multi(
                'AND',
                [
                    Criteria.equals('product.parentId', this.product.id),
                    Criteria.contains('product.states', 'is-download'),
                ],
            ));

            const generatedDigitalVariants = await this.pickwareProductRepository.search(criteria, Context.api);

            const updatedPickwareEntities = merge(generatedDigitalVariants, generatedDigitalVariants.map((item) => ({
                ...item,
                isStockManagementDisabled: true,
            })));

            await this.pickwareProductRepository.saveAll(updatedPickwareEntities, Context.api);
        },
    },
};
</script>
