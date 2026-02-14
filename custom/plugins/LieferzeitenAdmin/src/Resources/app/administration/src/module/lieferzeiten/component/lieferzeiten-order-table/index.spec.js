jest.mock('./lieferzeiten-order-table.html.twig', () => '', { virtual: true });
jest.mock('./lieferzeiten-order-table.scss', () => '', { virtual: true });

describe('lieferzeiten/component/lieferzeiten-order-table', () => {
    let methods;

    beforeEach(async () => {
        jest.resetModules();
        global.Shopware = {
            Component: {
                register: jest.fn(),
            },
        };

        await import('./index');
        methods = global.Shopware.Component.register.mock.calls[0][1].methods;
    });

    function createContext() {
        return {
            pickFirstDefined: methods.pickFirstDefined,
            displayOrDash: methods.displayOrDash,
        };
    }

    it('uses order level san6 fallback when no detailed positions exist', () => {
        const context = createContext();
        const order = { san6Position: '10', san6Pos: '11' };

        const value = methods.resolveSan6Position.call(context, order, []);

        expect(value).toBe('10');
    });

    it('uses san6Pos when san6Position is not available and no detailed positions exist', () => {
        const context = createContext();
        const order = { san6Pos: '11' };

        const value = methods.resolveSan6Position.call(context, order, []);

        expect(value).toBe('11');
    });

    it('uses order level quantity fallback when no detailed positions exist', () => {
        const context = createContext();
        const order = { quantity: 7 };

        const value = methods.resolveQuantity.call(context, order, []);

        expect(value).toBe('7');
    });

    it('uses positionsCount when quantity is not available and no detailed positions exist', () => {
        const context = createContext();
        const order = { positionsCount: 4 };

        const value = methods.resolveQuantity.call(context, order, []);

        expect(value).toBe('4');
    });

    it('prefers detailed positions over fallback values when available', () => {
        const context = createContext();
        const order = { san6Position: 'order-level', quantity: 99 };
        const positions = [
            { positionNumber: '1', quantity: 2 },
            { number: '2', orderedQuantity: 3 },
        ];

        const san6Position = methods.resolveSan6Position.call(context, order, positions);
        const quantity = methods.resolveQuantity.call(context, order, positions);

        expect(san6Position).toBe('1, 2');
        expect(quantity).toBe('5');
    });
});
