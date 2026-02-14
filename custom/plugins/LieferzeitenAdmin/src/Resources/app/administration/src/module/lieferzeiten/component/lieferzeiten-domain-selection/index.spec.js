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

    it('persists group + channel in localStorage when persistSelection is enabled', () => {
        const context = {
            persistSelection: true,
        };

        methods.persistDomainSelection.call(context, 'first-medical-e-commerce', 'ebay.de');

        expect(global.localStorage.setItem).toHaveBeenCalledWith('lieferzeitenManagementGroup', 'first-medical-e-commerce');
        expect(global.localStorage.setItem).toHaveBeenCalledWith('lieferzeitenManagementChannel', 'ebay.de');
        expect(global.sessionStorage.removeItem).toHaveBeenCalledWith('lieferzeitenManagementGroup');
        expect(global.sessionStorage.removeItem).toHaveBeenCalledWith('lieferzeitenManagementChannel');
    });

    it('confirms mandatory group step and emits group + channel selection', () => {
        const applySelection = jest.fn();

        const context = {
            draftGroup: 'medical-solutions',
            selectedChannel: 'ebay.de',
            canConfirmGroupSelection: true,
            showDomainModal: true,
            applySelection,
        };

        methods.confirmGroupSelection.call(context);

        expect(applySelection).toHaveBeenCalledWith('medical-solutions', 'medical-solutions-germany.de', true);
        expect(context.showDomainModal).toBe(false);
    });

    it('does not confirm when no area is selected', () => {
        const applySelection = jest.fn();
        const context = {
            draftGroup: null,
            canConfirmGroupSelection: false,
            applySelection,
        };

        methods.confirmGroupSelection.call(context);

        expect(applySelection).not.toHaveBeenCalled();
    });
});
