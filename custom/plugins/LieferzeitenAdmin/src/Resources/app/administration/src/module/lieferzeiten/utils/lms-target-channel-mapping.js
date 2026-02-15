import { normalizeDomainKey } from './domain-source-mapping';

/**
 * LMS channel matching rules (in priority order):
 * 1) Match by mapped sales-channel domain hostname only
 *    (canonical + legacy aliases via normalizeDomainKey).
 * 2) If no mapped domain exists, match by explicit technical identifiers only
 *    (technicalName, shortName, technical custom-field keys; exact match after normalization).
 *
 * Freely editable display labels (name, translated.name) are intentionally excluded
 * from technical matching to avoid false-positive LMS assignments after renames/translations.
 */
export const LMS_TARGET_CHANNELS = Object.freeze([
    {
        domainKey: 'first-medical-shop.de',
        technicalIdentifiers: ['first-medical-shop.de', 'first-medical-shop', 'first_medical_shop', 'first-medical-e-commerce'],
    },
    {
        domainKey: 'ebay.de',
        technicalIdentifiers: ['ebay.de', 'ebay_de', 'ebay-de'],
    },
    {
        domainKey: 'ebay.at',
        technicalIdentifiers: ['ebay.at', 'ebay_at', 'ebay-at'],
    },
    {
        domainKey: 'kaufland',
        technicalIdentifiers: ['kaufland'],
    },
    {
        domainKey: 'peg',
        technicalIdentifiers: ['peg'],
    },
    {
        domainKey: 'zonami',
        technicalIdentifiers: ['zonami'],
    },
    {
        domainKey: 'medical-solutions-germany.de',
        technicalIdentifiers: ['medical-solutions-germany.de', 'medical-solutions-germany', 'medical_solutions_germany'],
    },
]);

const TECHNICAL_IDENTIFIER_TO_DOMAIN_KEY = Object.freeze(
    LMS_TARGET_CHANNELS.reduce((acc, config) => {
        config.technicalIdentifiers.forEach((identifier) => {
            acc[normalizeIdentifier(identifier)] = config.domainKey;
        });

        return acc;
    }, {}),
);

function normalizeIdentifier(value) {
    return String(value || '').trim().toLowerCase();
}

function normalizeDomainFromUrl(url) {
    if (!url) {
        return null;
    }

    try {
        const parsedUrl = new URL(url);
        return normalizeDomainKey(parsedUrl.hostname);
    } catch (error) {
        return normalizeDomainKey(url);
    }
}

export function resolveLmsTargetChannelKey(channel) {
    const domainMatch = channel?.domains
        ?.map((domain) => normalizeDomainFromUrl(domain?.url))
        .find((domainKey) => domainKey !== null);

    if (domainMatch && LMS_TARGET_CHANNELS.some((config) => config.domainKey === domainMatch)) {
        return domainMatch;
    }

    // Important: display names are not technical identifiers and can be edited/translatable.
    // Matching must stay strict to domain hostnames and explicit technical keys only.
    const technicalIdentifierCandidates = [
        channel?.technicalName,
        channel?.shortName,
        channel?.customFields?.technicalIdentifier,
        channel?.customFields?.technicalName,
        channel?.customFields?.technical_name,
        channel?.customFields?.technical_identifier,
    ]
        .map((identifier) => normalizeIdentifier(identifier))
        .filter((identifier) => identifier.length > 0);

    return technicalIdentifierCandidates
        .map((identifier) => TECHNICAL_IDENTIFIER_TO_DOMAIN_KEY[identifier] || null)
        .find((domainKey) => domainKey !== null) || null;
}

export function isLmsTargetChannel(channel) {
    return resolveLmsTargetChannelKey(channel) !== null;
}
