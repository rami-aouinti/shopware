import { registerComponent } from '@pickware/shopware-component-initializer';
import * as PwErpStockManagementChangeConfirmationModal
    from '@pickware-erp-bundle/product/pw-erp-stock-management-change-confirmation-modal.vue';

import * as SwPriceFieldOverride from './sw-price-field.vue';
import * as SwProductDeliveryFormOverride from './sw-product-deliverability-form.vue';
import * as SwProductDetailOverride from './sw-product-detail.vue';
import * as SwProductDetailVariantOverride
    from './sw-product-detail-variants.vue';
import * as SwProductListOverride from './sw-product-list.vue';

registerComponent(SwPriceFieldOverride);
registerComponent(SwProductDeliveryFormOverride);
registerComponent(PwErpStockManagementChangeConfirmationModal);
registerComponent(SwProductDetailOverride);
registerComponent(SwProductListOverride);
registerComponent(SwProductDetailVariantOverride);
