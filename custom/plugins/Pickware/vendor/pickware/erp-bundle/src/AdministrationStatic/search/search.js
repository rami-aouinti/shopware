import { registerComponent } from '@pickware/shopware-component-initializer';

import * as SwProfileIndexSearchPreferencesOverride from './sw-profile-index-search-preferences.vue';
import * as SwSearchBarOverride from './sw-search-bar.vue';
import * as SwSearchBarItemOverride from './sw-search-bar-item.vue';

registerComponent(SwProfileIndexSearchPreferencesOverride);
registerComponent(SwSearchBarItemOverride);
registerComponent(SwSearchBarOverride);
