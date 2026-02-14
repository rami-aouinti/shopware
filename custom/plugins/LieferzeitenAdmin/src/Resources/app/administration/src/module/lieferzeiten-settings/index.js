import './page/lieferzeiten-channel-settings-list';
import './page/lieferzeiten-task-assignment-rule-list';
import './page/lieferzeiten-notification-toggle-list';

const { Module } = Shopware;

Module.register('lieferzeiten-settings', {
    type: 'plugin',
    name: 'lieferzeiten-settings',
    title: 'lieferzeiten.lms.general.mainMenuItem',
    description: 'lieferzeiten.lms.general.description',
    color: '#009ee3',
    icon: 'regular-cog',

    routes: {
        index: {
            component: 'lieferzeiten-channel-settings-list',
            path: '',
            meta: {
                privilege: 'admin',
            },
        },
        channelSettings: {
            component: 'lieferzeiten-channel-settings-list',
            path: 'channel-settings',
            meta: {
                privilege: 'admin',
            },
        },
        taskAssignmentRules: {
            component: 'lieferzeiten-task-assignment-rule-list',
            path: 'task-assignment-rules',
            meta: {
                privilege: 'admin',
            },
        },
        notificationToggles: {
            component: 'lieferzeiten-notification-toggle-list',
            path: 'notification-toggles',
            meta: {
                privilege: 'admin',
            },
        },
    },

    navigation: [
        {
            id: 'lieferzeiten-settings',
            label: 'lieferzeiten.lms.general.mainMenuItem',
            color: '#009ee3',
            path: 'lieferzeiten.settings.index',
            icon: 'regular-cog',
            parent: 'sw-order',
            position: 90,
        },
        {
            id: 'lieferzeiten-settings-channel',
            label: 'lieferzeiten.lms.navigation.thresholdsByChannel',
            color: '#009ee3',
            path: 'lieferzeiten.settings.index',
            parent: 'lieferzeiten-settings',
            position: 10,
        },
        {
            id: 'lieferzeiten-settings-task',
            label: 'lieferzeiten.lms.navigation.taskAssignmentRules',
            color: '#009ee3',
            path: 'lieferzeiten.settings.taskAssignmentRules',
            parent: 'lieferzeiten-settings',
            position: 20,
        },
        {
            id: 'lieferzeiten-settings-notifications',
            label: 'lieferzeiten.lms.navigation.notificationToggles',
            color: '#009ee3',
            path: 'lieferzeiten.settings.notificationToggles',
            parent: 'lieferzeiten-settings',
            position: 30,
        },
    ],
});
