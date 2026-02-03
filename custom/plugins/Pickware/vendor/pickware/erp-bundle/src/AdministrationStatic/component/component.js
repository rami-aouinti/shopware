import { registerComponent } from '@pickware/shopware-component-initializer';

import * as SwSidebarOverride from './sw-sidebar.vue';
import * as SwSidebarItemOverride from './sw-sidebar-item.vue';

registerComponent(SwSidebarItemOverride);
registerComponent(SwSidebarOverride);
