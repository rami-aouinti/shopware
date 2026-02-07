import { registerComponent } from '@pickware/shopware-component-initializer';

import * as SwFilterPanelOverride from './sw-filter-panel.vue';
import * as SwOrderListOverride from './sw-order-list.vue';

registerComponent(SwFilterPanelOverride);
registerComponent(SwOrderListOverride);
