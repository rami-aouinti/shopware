import { registerComponent } from '@pickware/shopware-component-initializer';
import * as PwErpBulkEditOrderInvoiceCorrectionFormFields
    from '@pickware-erp-bundle/invoice-correction/bulk-edit/pw-erp-bulk-edit-order-invoice-correction-form-fields.vue';
import * as SwBulkEditOrderOverride from '@pickware-erp-bundle/invoice-correction/bulk-edit/sw-bulk-edit-order.vue';
import * as SwBulkEditSaveModalProcessOverride
    from '@pickware-erp-bundle/invoice-correction/bulk-edit/sw-bulk-edit-save-modal-process.vue';

import * as SwBulkEditOrderDocumentsDownloadDocumentsOverride from
    './bulk-edit/sw-bulk-edit-order-documents-download-documents.vue';
import * as SwBulkEditSaveModalSuccessOverride
    from './bulk-edit/sw-bulk-edit-save-modal-success.vue';
import * as SwOrderDocumentCardOverrideOverride from './sw-order-document-card.vue';
import * as SwOrderDocumentSettingsPickwareErpInvoiceCorrectionModal
    from './sw-order-document-settings-pickware-erp-invoice-correction-modal.vue';

registerComponent(PwErpBulkEditOrderInvoiceCorrectionFormFields);
registerComponent(SwOrderDocumentCardOverrideOverride);
registerComponent(SwBulkEditOrderOverride);
registerComponent(SwOrderDocumentSettingsPickwareErpInvoiceCorrectionModal);
registerComponent(SwBulkEditSaveModalProcessOverride);
registerComponent(SwBulkEditSaveModalSuccessOverride);
registerComponent(SwBulkEditOrderDocumentsDownloadDocumentsOverride);

