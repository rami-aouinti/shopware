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

    it('does not match by display names when no technical identifier exists', () => {
        const channel = {
            name: 'ebay.de',
            translated: { name: 'ebay_de' },
            domains: [],
        };

        expect(resolveLmsTargetChannelKey(channel)).toBeNull();
    });
});
