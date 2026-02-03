import { registerComponent } from '@pickware/shopware-component-initializer';

import * as SwBulkEditOrder from './bulk-edit/sw-bulk-edit-order.vue';
import * as SwBulkEditSaveModalProcess
    from './bulk-edit/sw-bulk-edit-save-modal-process.vue';
import * as SwBulkEditSaveModalSuccess
    from './bulk-edit/sw-bulk-edit-save-modal-success.vue';
import * as SwOrderDocumentCardOverride from './sw-order-document-card.vue';
import * as SwOrderDocumentSettingsPickwareErpPicklistModal
    from './sw-order-document-settings-pickware-erp-picklist-modal.vue';

registerComponent(SwOrderDocumentCardOverride);
registerComponent(SwOrderDocumentSettingsPickwareErpPicklistModal);
registerComponent(SwBulkEditOrder);
registerComponent(SwBulkEditSaveModalProcess);
registerComponent(SwBulkEditSaveModalSuccess);
