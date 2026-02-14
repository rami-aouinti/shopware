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


    it('disables comment save when order has no positions', () => {
        const context = {
            hasEditAccess: () => true,
            getValidCommentTargetPositionId: methods.getValidCommentTargetPositionId,
            resolveCommentTargetPositionId: methods.resolveCommentTargetPositionId,
            isOpenPosition: methods.isOpenPosition,
        };
        const order = { positions: [], commentTargetPositionId: null };

        const canSave = methods.canSaveComment.call(context, order);

        expect(canSave).toBe(false);
    });

    it('does not call updateComment for order without positions', async () => {
        const updateComment = jest.fn();
        const context = {
            canSaveComment: methods.canSaveComment,
            hasEditAccess: () => true,
            getValidCommentTargetPositionId: methods.getValidCommentTargetPositionId,
            ensureCommentTargetPositionId: methods.ensureCommentTargetPositionId,
            resolveCommentTargetPositionId: methods.resolveCommentTargetPositionId,
            isOpenPosition: methods.isOpenPosition,
            $set: jest.fn(),
            createNotificationError: jest.fn(),
            createNotificationSuccess: jest.fn(),
            resolveConcurrencyToken: jest.fn(() => null),
            reloadOrder: jest.fn(),
            handleConflictError: jest.fn(() => false),
            lieferzeitenOrdersService: { updateComment },
            $t: (key) => key,
        };

        await methods.saveComment.call(context, { positions: [], comment: 'foo', commentTargetPositionId: null });

        expect(updateComment).not.toHaveBeenCalled();
    });

});
