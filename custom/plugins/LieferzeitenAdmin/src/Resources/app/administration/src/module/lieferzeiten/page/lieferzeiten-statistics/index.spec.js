jest.mock('./lieferzeiten-statistics.html.twig', () => '', { virtual: true });
jest.mock('./lieferzeiten-statistics.scss', () => '', { virtual: true });

describe('lieferzeiten/page/lieferzeiten-statistics', () => {
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

    function createContext({ viewer = true, editor = true } = {}) {
        return {
            hasViewAccess: () => viewer,
            hasEditAccess: () => editor,
            navigateToOrder: jest.fn(),
            navigateToTask: jest.fn(),
            performTaskAction: jest.fn(),
            navigateToTracking: jest.fn(),
            getActionLoadingKey: methods.getActionLoadingKey,
            actionLoadingByActivity: {},
            $t: (key) => key,
        };
    }

    it('returns order and tracking actions for paket events', () => {
        const context = createContext();
        const activity = {
            id: 'a1',
            eventType: 'paket',
            paketId: 'p1',
            orderNumber: '1001',
            trackingNumber: 'TRACK-1',
        };

        const actions = methods.resolveActivityActions.call(context, activity);

        expect(actions).toHaveLength(2);
        expect(actions[0].id).toBe('open-order');
        expect(actions[0].disabled).toBe(false);
        expect(actions[1].id).toBe('open-tracking');
        expect(actions[1].disabled).toBe(false);
    });

    it('returns task route + close action for open task events', () => {
        const context = createContext();
        const activity = {
            id: 'a2',
            eventType: 'task',
            taskId: 't1',
            status: 'open',
        };

        const actions = methods.resolveActivityActions.call(context, activity);

        expect(actions).toHaveLength(2);
        expect(actions[0].id).toBe('open-task');
        expect(actions[1].id).toBe('task-close');
        expect(actions[1].disabled).toBe(false);
    });

    it('disables task transition when editor acl is missing', () => {
        const context = createContext({ viewer: true, editor: false });
        const activity = {
            id: 'a3',
            eventType: 'task',
            taskId: 't2',
            status: 'done',
        };

        const actions = methods.resolveActivityActions.call(context, activity);

        const transitionAction = actions.find((action) => action.id === 'task-reopen');

        expect(transitionAction).toBeDefined();
        expect(transitionAction.disabled).toBe(true);
        expect(transitionAction.disabledReason).toBe('lieferzeiten.statistics.actionReasons.noEditAcl');
    });

    it('returns a disabled placeholder action when event type has no mapping', () => {
        const context = createContext();
        const activity = {
            id: 'a4',
            eventType: 'unknown',
        };

        const actions = methods.resolveActivityActions.call(context, activity);

        expect(actions).toHaveLength(1);
        expect(actions[0].id).toBe('unavailable');
        expect(actions[0].disabled).toBe(true);
    });
});
