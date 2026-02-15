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
export const DEFAULT_GROUP_ID = 'first-medical-e-commerce';

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

const LEGACY_GROUP_KEY_MAPPING = {
    'first medical': DEFAULT_GROUP_ID,
    'e-commerce': DEFAULT_GROUP_ID,
    'first medical - e-commerce': DEFAULT_GROUP_ID,
    'first-medical-e-commerce': DEFAULT_GROUP_ID,
    'medical solutions': 'medical-solutions',
    'medical-solutions': 'medical-solutions',
};

export function getChannelsForGroup(groupId) {
    return DOMAIN_GROUPS.find((group) => group.id === groupId)?.channels || [];
}

export function getDefaultDomainForGroup(groupId) {
    return getChannelsForGroup(groupId)[0]?.value || null;
}

export function normalizeGroupKey(groupId) {
    const normalized = String(groupId || '').trim().toLowerCase();
    if (!normalized) {
        return null;
    }

    if (LEGACY_GROUP_KEY_MAPPING[normalized]) {
        return LEGACY_GROUP_KEY_MAPPING[normalized];
    }

    return DOMAIN_GROUPS.some((group) => group.id === normalized) ? normalized : null;
}

export function resolveGroupKeyForDomain(domain) {
    const normalizedDomain = normalizeDomainKey(domain);
    if (!normalizedDomain) {
        return null;
    }

    return DOMAIN_GROUPS.find((group) => group.channels.some((channel) => channel.value === normalizedDomain))?.id || null;
}

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
