import { resolveLmsTargetChannelKey } from './lms-target-channel-mapping';

describe('lieferzeiten/utils/lms-target-channel-mapping', () => {
    it('matches by mapped domain hostname', () => {
        const channel = {
            name: 'Renamed storefront label',
            translated: { name: 'Umbenannter Storefront-Name' },
            domains: [{ url: 'https://first-medical-shop.de/path' }],
        };

        expect(resolveLmsTargetChannelKey(channel)).toBe('first-medical-shop.de');
    });

    it('matches by explicit technical identifier custom field', () => {
        const channel = {
            name: 'Custom display name',
            customFields: {
                technical_identifier: 'ebay_de',
            },
        };

        expect(resolveLmsTargetChannelKey(channel)).toBe('ebay.de');
    });

    it('keeps LMS mapping stable when only display names change', () => {
        const technicalChannel = {
            technicalName: 'first-medical-shop',
            name: 'Original display label',
            translated: { name: 'Originale Übersetzung' },
        };

        const renamedChannel = {
            ...technicalChannel,
            name: 'Completely renamed display label',
            translated: { name: 'Komplett umbenannte Übersetzung' },
        };

        expect(resolveLmsTargetChannelKey(technicalChannel)).toBe('first-medical-shop.de');
        expect(resolveLmsTargetChannelKey(renamedChannel)).toBe('first-medical-shop.de');
    });

    it('does not match by display names when no technical identifier exists', () => {
        const channel = {
            name: 'ebay.de',
            translated: { name: 'ebay_de' },
            domains: [],
        };

        expect(resolveLmsTargetChannelKey(channel)).toBeNull();
    });
});
