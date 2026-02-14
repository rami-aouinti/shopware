export const DOMAIN_GROUPS = [
    {
        id: 'first-medical-e-commerce',
        labelSnippet: 'lieferzeiten.domainSelection.groups.firstMedicalECommerce',
        channels: [
            {
                value: 'first-medical-shop.de',
                labelSnippet: 'lieferzeiten.domainSelection.channels.firstMedicalShop',
            },
            {
                value: 'ebay.de',
                labelSnippet: 'lieferzeiten.domainSelection.channels.ebayDe',
            },
            {
                value: 'ebay.at',
                labelSnippet: 'lieferzeiten.domainSelection.channels.ebayAt',
            },
            {
                value: 'kaufland',
                labelSnippet: 'lieferzeiten.domainSelection.channels.kaufland',
            },
            {
                value: 'peg',
                labelSnippet: 'lieferzeiten.domainSelection.channels.peg',
            },
            {
                value: 'zonami',
                labelSnippet: 'lieferzeiten.domainSelection.channels.zonami',
            },
        ],
    },
    {
        id: 'medical-solutions',
        labelSnippet: 'lieferzeiten.domainSelection.groups.medicalSolutions',
        channels: [
            {
                value: 'medical-solutions-germany.de',
                labelSnippet: 'lieferzeiten.domainSelection.channels.medicalSolutionsGermany',
            },
        ],
    },
];

export const DEFAULT_DOMAIN_KEY = 'first-medical-shop.de';

const DOMAIN_SOURCE_MAPPING = {
    'first-medical-shop.de': ['first-medical-shop.de', 'first medical', 'e-commerce', 'shopware', 'gambio', 'first-medical-e-commerce'],
    'ebay.de': ['ebay.de', 'ebay de'],
    'ebay.at': ['ebay.at', 'ebay at'],
    kaufland: ['kaufland'],
    peg: ['peg'],
    zonami: ['zonami'],
    'medical-solutions-germany.de': ['medical-solutions-germany.de', 'medical solutions', 'medical-solutions', 'medical_solutions'],
};

const LEGACY_DOMAIN_KEY_MAPPING = {
    'first medical': DEFAULT_DOMAIN_KEY,
    'e-commerce': DEFAULT_DOMAIN_KEY,
    'first medical - e-commerce': DEFAULT_DOMAIN_KEY,
    'first-medical-e-commerce': DEFAULT_DOMAIN_KEY,
    'medical solutions': 'medical-solutions-germany.de',
    'medical-solutions': 'medical-solutions-germany.de',
};

export function normalizeDomainKey(domain) {
    const normalized = String(domain || '').trim().toLowerCase();
    if (!normalized) {
        return null;
    }

    if (LEGACY_DOMAIN_KEY_MAPPING[normalized]) {
        return LEGACY_DOMAIN_KEY_MAPPING[normalized];
    }

    if (DOMAIN_SOURCE_MAPPING[normalized]) {
        return normalized;
    }

    return null;
}

export function resolveDomainKeyForSourceSystem(sourceSystem) {
    const normalizedSource = String(sourceSystem || '').trim().toLowerCase();
    if (!normalizedSource) {
        return null;
    }

    const mappedDomain = Object.entries(DOMAIN_SOURCE_MAPPING)
        .find(([, sources]) => sources.includes(normalizedSource));

    return mappedDomain ? mappedDomain[0] : normalizeDomainKey(normalizedSource);
}
