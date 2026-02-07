import './pw-erp-order-pickability/pw-erp-order-pickability.js';

import { registerComponent } from '@pickware/shopware-component-initializer';

import * as SwOrderSelectDocumentTypeModal
    from '@pickware-erp-bundle/order/sw-order-detail/sw-order-select-document-type-modal.vue';
import * as BulkEditOrderOverride from './bulk-edit/sw-bulk-edit-order.vue';
import * as BulkEditSaveModalProcess from './bulk-edit/sw-bulk-edit-save-modal-process.vue';
import * as SwOrderDetailOverride from './sw-order-detail/sw-order-detail.vue';
import * as SwOrderDetailGeneralOverride from './sw-order-detail/sw-order-detail-general.vue';
import * as SwOrderLineItemsGridOverride from './sw-order-detail/sw-order-line-items-grid.vue';
import * as SwOrderStateHistoryCardOverride from './sw-order-detail/sw-order-state-history-card.vue';
import * as SwOrderGeneralInfoOverride from './sw-order-general-info/sw-order-general-info.vue';

registerComponent(BulkEditOrderOverride);
registerComponent(BulkEditSaveModalProcess);
registerComponent(SwOrderDetailOverride);
registerComponent(SwOrderDetailGeneralOverride);
registerComponent(SwOrderLineItemsGridOverride);
registerComponent(SwOrderStateHistoryCardOverride);
registerComponent(SwOrderSelectDocumentTypeModal);
registerComponent(SwOrderGeneralInfoOverride);
