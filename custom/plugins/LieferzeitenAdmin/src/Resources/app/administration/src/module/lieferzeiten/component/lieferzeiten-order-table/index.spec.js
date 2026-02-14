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



    it('formats position quantity as shipped/ordered ratio for complete and fractional cases', () => {
        const context = {
            parseQuantity: methods.parseQuantity,
            positionQuantitySuffix: methods.positionQuantitySuffix,
            pickFirstDefined: methods.pickFirstDefined,
            displayOrDash: methods.displayOrDash,
            $t: (key) => ({
                'lieferzeiten.shipping.pieces': 'Stück',
            }[key] || key),
        };

        expect(methods.positionQuantityDisplay.call(context, { orderedQuantity: 3, shippedQuantity: 3 })).toBe('3/3 Stück');
        expect(methods.positionQuantityDisplay.call(context, { orderedQuantity: 3, shippedQuantity: 2 })).toBe('2/3 Stück');
        expect(methods.positionQuantityDisplay.call(context, { quantity: 5 })).toBe('5');
    });

    it('returns expected shipping labels per position state', () => {
        const context = {
            $t: (key) => ({
                'lieferzeiten.shipping.unclear': 'Unklar',
                'lieferzeiten.shipping.completeShipment': 'Gesamt-Versand',
                'lieferzeiten.shipping.partialShipment': 'Teillieferung',
                'lieferzeiten.shipping.splitPosition': 'Trennung Auftragsposition',
                'lieferzeiten.shipping.pieces': 'Stück',
            }[key] || key),
            normalizeShippingType: methods.normalizeShippingType,
            parseQuantity: methods.parseQuantity,
            positionQuantitySuffix: methods.positionQuantitySuffix,
        };

        expect(methods.shippingLabelForPosition.call(context, {}, null)).toBe('Unklar');
        expect(methods.shippingLabelForPosition.call(context, { shippingAssignmentType: 'gesamt' }, {})).toBe('Gesamt-Versand');
        expect(methods.shippingLabelForPosition.call(context, { shippingAssignmentType: 'teil' }, { orderedQuantity: 3, shippedQuantity: 3 })).toBe('Teillieferung 3/3 Stück');
        expect(methods.shippingLabelForPosition.call(context, { shippingAssignmentType: 'teil' }, { orderedQuantity: 3, shippedQuantity: 2 })).toBe('Teillieferung 2/3 Stück');
        expect(methods.shippingLabelForPosition.call(context, { shippingAssignmentType: 'trennung' }, { orderedQuantity: 5, shippedQuantity: 2 })).toBe('Trennung Auftragsposition 2/5 Stück');
    });

    it('does not notify on initial task snapshot to avoid false positives on reload', () => {
        const createNotificationInfo = jest.fn();
        const context = {
            extractAdditionalDeliveryRequestTaskStatusByPosition: methods.extractAdditionalDeliveryRequestTaskStatusByPosition,
            isAdditionalDeliveryRequestTaskClosed: methods.isAdditionalDeliveryRequestTaskClosed,
            createNotificationInfo,
            additionalRequestTaskStatusByPosition: {},
            additionalRequestTaskInitialized: false,
            $t: (key) => key,
        };

        methods.handleAdditionalDeliveryRequestTaskTransitions.call(context, [{
            positions: [{
                id: 'position-1',
                additionalDeliveryRequestTask: { status: 'done', initiator: 'John Doe', closedAt: '2025-01-01 10:00:00' },
            }],
        }]);

        expect(createNotificationInfo).not.toHaveBeenCalled();
        expect(context.additionalRequestTaskInitialized).toBe(true);
        expect(context.additionalRequestTaskStatusByPosition['position-1'].status).toBe('done');
    });

    it('notifies only when additional delivery request task really transitions to done/cancelled', () => {
        const createNotificationInfo = jest.fn();
        const context = {
            extractAdditionalDeliveryRequestTaskStatusByPosition: methods.extractAdditionalDeliveryRequestTaskStatusByPosition,
            isAdditionalDeliveryRequestTaskClosed: methods.isAdditionalDeliveryRequestTaskClosed,
            createNotificationInfo,
            additionalRequestTaskStatusByPosition: {
                'position-1': { status: 'open', closedAt: null, initiator: 'Jane Doe' },
            },
            additionalRequestTaskInitialized: true,
            $t: (key) => key,
        };

        methods.handleAdditionalDeliveryRequestTaskTransitions.call(context, [{
            positions: [{
                id: 'position-1',
                additionalDeliveryRequestTask: { status: 'cancelled', initiator: 'Jane Doe', closedAt: '2025-01-02 10:00:00' },
            }],
        }]);

        expect(createNotificationInfo).toHaveBeenCalledTimes(1);
        expect(createNotificationInfo.mock.calls[0][0].message).toContain('lieferzeiten.additionalRequest.notificationClosed');

        methods.handleAdditionalDeliveryRequestTaskTransitions.call(context, [{
            positions: [{
                id: 'position-1',
                additionalDeliveryRequestTask: { status: 'cancelled', initiator: 'Jane Doe', closedAt: '2025-01-02 10:00:00' },
            }],
        }]);

        expect(createNotificationInfo).toHaveBeenCalledTimes(1);
    });

});
