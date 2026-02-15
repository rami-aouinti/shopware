jest.mock('./lieferzeiten-index.html.twig', () => '', { virtual: true });

jest.mock('../../utils/domain-source-mapping', () => ({
    normalizeDomainKey: (value) => value || null,
    resolveDomainKeyForSourceSystem: (value) => value || null,
}));

describe('lieferzeiten/page/lieferzeiten-index', () => {
    let component;

    beforeEach(async () => {
        jest.resetModules();
        global.Shopware = {
            Component: {
                register: jest.fn(),
            },
        };

        await import('./index');
        component = global.Shopware.Component.register.mock.calls[0][1];
    });

    it('keeps main views locked until both bereich and kanal are selected', () => {
        const context = {
            selectedBereich: 'first-medical-e-commerce',
            selectedDomain: null,
        };

        expect(component.computed.canAccessMainViews.call(context)).toBe(false);

        context.selectedDomain = 'ebay.de';

        expect(component.computed.canAccessMainViews.call(context)).toBe(true);
    });


    it('buildFilterParams returns values from default filters on first render', () => {
        const context = {
            filters: component.data().filters,
        };

        context.filters.bestellnummer = '  BN-1001  ';
        context.filters.status = 'offen';

        expect(component.methods.buildFilterParams.call(context)).toEqual({
            bestellnummer: 'BN-1001',
            status: 'offen',
        });
    });
});
