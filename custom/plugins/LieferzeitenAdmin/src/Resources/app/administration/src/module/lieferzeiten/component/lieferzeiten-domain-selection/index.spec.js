jest.mock('./lieferzeiten-domain-selection.html.twig', () => '', { virtual: true });

jest.mock('../../utils/domain-source-mapping', () => ({
    DEFAULT_GROUP_ID: 'first-medical-e-commerce',
    DOMAIN_GROUPS: [
        {
            id: 'first-medical-e-commerce',
            labelSnippet: 'group.fm',
            channels: [
                { value: 'first-medical-shop.de', labelSnippet: 'channel.fm' },
                { value: 'ebay.de', labelSnippet: 'channel.ebay' },
            ],
        },
        {
            id: 'medical-solutions',
            labelSnippet: 'group.ms',
            channels: [
                { value: 'medical-solutions-germany.de', labelSnippet: 'channel.ms' },
            ],
        },
    ],
    getChannelsForGroup: (groupId) => ({
        'first-medical-e-commerce': [
            { value: 'first-medical-shop.de', labelSnippet: 'channel.fm' },
            { value: 'ebay.de', labelSnippet: 'channel.ebay' },
        ],
        'medical-solutions': [
            { value: 'medical-solutions-germany.de', labelSnippet: 'channel.ms' },
        ],
    }[groupId] || []),
    getDefaultDomainForGroup: (groupId) => ({
        'first-medical-e-commerce': 'first-medical-shop.de',
        'medical-solutions': 'medical-solutions-germany.de',
    }[groupId] || null),
    normalizeDomainKey: (value) => value || null,
    normalizeGroupKey: (value) => value || null,
    resolveGroupKeyForDomain: (domain) => (domain === 'medical-solutions-germany.de' ? 'medical-solutions' : 'first-medical-e-commerce'),
}));

describe('lieferzeiten/component/lieferzeiten-domain-selection', () => {
    let methods;

    beforeEach(async () => {
        jest.resetModules();
        global.Shopware = {
            Component: {
                register: jest.fn(),
            },
        };

        global.localStorage = {
            getItem: jest.fn(() => null),
            setItem: jest.fn(),
            removeItem: jest.fn(),
        };

        global.sessionStorage = {
            getItem: jest.fn(() => null),
            setItem: jest.fn(),
            removeItem: jest.fn(),
        };

        await import('./index');
        methods = global.Shopware.Component.register.mock.calls[0][1].methods;
    });

    it('persists bereich + kanal in localStorage when persistSelection is enabled', () => {
        const context = {
            persistSelection: true,
        };

        methods.persistDomainSelection.call(context, 'first-medical-e-commerce', 'ebay.de');

        expect(global.localStorage.setItem).toHaveBeenCalledWith('lieferzeitenManagementBereich', 'first-medical-e-commerce');
        expect(global.localStorage.setItem).toHaveBeenCalledWith('lieferzeitenManagementChannel', 'ebay.de');
        expect(global.localStorage.setItem).toHaveBeenCalledWith('lieferzeitenManagementDomain', 'ebay.de');
        expect(global.sessionStorage.removeItem).toHaveBeenCalledWith('lieferzeitenManagementBereich');
        expect(global.sessionStorage.removeItem).toHaveBeenCalledWith('lieferzeitenManagementChannel');
    });

    it('migrates legacy localStorage values to new keys', () => {
        global.localStorage.getItem = jest.fn((key) => ({
            lieferzeitenManagementGroup: 'medical-solutions',
            lieferzeitenManagementDomain: 'medical-solutions-germany.de',
        }[key] ?? null));

        const context = {
            value: null,
            persistSelection: false,
            applySelection: jest.fn(),
            persistDomainSelection: jest.fn(),
            getStoredSelection: methods.getStoredSelection,
            resolveInitialGroup: methods.resolveInitialGroup,
            $emit: jest.fn(),
        };

        methods.loadStoredSelection.call(context);

        expect(context.persistSelection).toBe(true);
        expect(context.applySelection).toHaveBeenCalledWith('medical-solutions', 'medical-solutions-germany.de', true, false);
        expect(context.persistDomainSelection).toHaveBeenCalledWith('medical-solutions', 'medical-solutions-germany.de');
    });

    it('resolves bereich from channel when only legacy domain is present', () => {
        const storage = {
            getItem: jest.fn((key) => ({
                lieferzeitenManagementDomain: 'medical-solutions-germany.de',
            }[key] ?? null)),
        };

        const context = {
            resolveInitialGroup: methods.resolveInitialGroup,
        };

        const selection = methods.getStoredSelection.call(context, storage);

        expect(selection.bereich).toBe('medical-solutions');
        expect(selection.channel).toBe('medical-solutions-germany.de');
    });

    it('resets both bereich and kanal and emits null values', () => {
        const context = {
            selectedBereich: 'first-medical-e-commerce',
            selectedChannel: 'ebay.de',
            $emit: jest.fn(),
        };

        methods.resetDomainSelection.call(context);

        expect(context.selectedBereich).toBeNull();
        expect(context.selectedChannel).toBeNull();
        expect(context.$emit).toHaveBeenCalledWith('bereich-change', null);
        expect(context.$emit).toHaveBeenCalledWith('input', null);
    });
});
