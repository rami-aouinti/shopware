const INTERCEPTOR_FLAG = '__lieferzeitenStoreApiFallbackInterceptorInstalled';
const STORE_UPDATES_PATH = '/api/_action/store/updates';
const EXTENSION_INSTALLED_PATH = '/api/_action/extension/installed';

function isKnownStoreEndpoint(url = '') {
    return url.includes(STORE_UPDATES_PATH) || url.includes(EXTENSION_INSTALLED_PATH);
}

function createFallbackResponse(error) {
    const config = error?.config || {};
    const url = String(config.url || '');

    if (url.includes(STORE_UPDATES_PATH)) {
        return {
            data: {
                total: 0,
                updates: [],
                items: [],
            },
            status: 200,
            statusText: 'OK',
            headers: {},
            config,
        };
    }

    return {
        data: {
            data: [],
            total: 0,
        },
        status: 200,
        statusText: 'OK',
        headers: {},
        config,
    };
}

function installStoreApiFallbackInterceptor() {
    if (window[INTERCEPTOR_FLAG]) {
        return;
    }

    const initContainer = Shopware.Application.getContainer('init');
    const httpClient = initContainer?.httpClient;

    if (!httpClient?.interceptors?.response) {
        return;
    }

    httpClient.interceptors.response.use(
        (response) => response,
        (error) => {
            const status = error?.response?.status;
            const url = String(error?.config?.url || '');

            if (status === 500 && isKnownStoreEndpoint(url)) {
                // eslint-disable-next-line no-console
                console.warn('[LieferzeitenAdmin] Store API unavailable, using empty fallback response for:', url);
                return Promise.resolve(createFallbackResponse(error));
            }

            return Promise.reject(error);
        },
    );

    window[INTERCEPTOR_FLAG] = true;
}

installStoreApiFallbackInterceptor();
