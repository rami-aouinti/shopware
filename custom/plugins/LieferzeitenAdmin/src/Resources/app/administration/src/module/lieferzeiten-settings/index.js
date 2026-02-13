import './page/lieferzeiten-channel-settings-list';
import './page/lieferzeiten-task-assignment-rule-list';
import './page/lieferzeiten-notification-toggle-list';

const { Module } = Shopware;

Module.register('lieferzeiten-settings', {
    type: 'plugin',
    name: 'lieferzeiten-settings',
    title: 'Lieferzeiten Settings',
    description: 'Einstellungen für Lieferzeiten',
    color: '#009ee3',
    icon: 'regular-cog',

    routes: {
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
            label: 'Lieferzeiten Settings',
            color: '#009ee3',
            path: 'lieferzeiten.settings.channelSettings',
            icon: 'regular-cog',
            parent: 'sw-settings',
            position: 90,
        },
        {
            id: 'lieferzeiten-settings-channel',
            label: 'Channel Settings',
            color: '#009ee3',
            path: 'lieferzeiten.settings.channelSettings',
            parent: 'lieferzeiten-settings',
            position: 10,
        },
        {
            id: 'lieferzeiten-settings-task',
            label: 'Task Assignment Rules',
            color: '#009ee3',
            path: 'lieferzeiten.settings.taskAssignmentRules',
            parent: 'lieferzeiten-settings',
            position: 20,
        },
        {
            id: 'lieferzeiten-settings-notifications',
            label: 'Notification Toggles',
            color: '#009ee3',
            path: 'lieferzeiten.settings.notificationToggles',
            parent: 'lieferzeiten-settings',
            position: 30,
        },
    ],
});
