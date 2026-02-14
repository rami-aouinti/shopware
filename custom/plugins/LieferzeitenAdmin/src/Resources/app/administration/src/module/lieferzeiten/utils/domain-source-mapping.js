export const DOMAIN_OPTIONS = [
    { value: 'first-medical-e-commerce', label: 'First Medical - E-Commerce' },
    { value: 'medical-solutions', label: 'Medical Solutions' },
];

const DOMAIN_SOURCE_MAPPING = {
    'first-medical-e-commerce': ['first medical', 'e-commerce', 'shopware', 'gambio'],
    'medical-solutions': ['medical solutions', 'medical-solutions', 'medical_solutions'],
};

const LEGACY_DOMAIN_KEY_MAPPING = {
    'first medical': 'first-medical-e-commerce',
    'e-commerce': 'first-medical-e-commerce',
    'first medical - e-commerce': 'first-medical-e-commerce',
    'medical solutions': 'medical-solutions',
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

