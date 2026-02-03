import { registerComponent } from '@pickware/shopware-component-initializer';

import * as SwBulkEditOrderDocuments from './sw-bulk-edit-order-documents.vue';
import * as SwFlowGenerateDocumentModal from './sw-flow-generate-document-modal.vue';
import * as SwOrderDocumentCard from './sw-order-document-card.vue';
import * as SwSettingsDocumentDetail from './sw-settings-document-detail.vue';

registerComponent(SwFlowGenerateDocumentModal);
registerComponent(SwOrderDocumentCard);
registerComponent(SwSettingsDocumentDetail);
registerComponent(SwBulkEditOrderDocuments);
