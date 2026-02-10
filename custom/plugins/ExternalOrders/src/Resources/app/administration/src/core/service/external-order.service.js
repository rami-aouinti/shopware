const { ApiService } = Shopware.Classes;

class ExternalOrderService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'external-orders') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'externalOrderService';
    }

    async list({ channel = null, search = null } = {}) {
        const params = {};
        if (channel) {
            params.channel = channel;
        }
        if (search) {
            params.search = search;
        }

        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/list`, {
            headers: this.getBasicHeaders(),
            params,
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data;
    }

    async detail(orderId) {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/detail/${orderId}`, {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data;
    }


    async runSyncNow() {
        const response = await this.httpClient.post(`_action/${this.getApiBasePath()}/sync-now`, {}, {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data;
    }

    async getSyncStatus() {
        const response = await this.httpClient.get(`_action/${this.getApiBasePath()}/sync-status`, {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data;
    }

    async seedTestData() {
        const response = await this.httpClient.post(`_action/${this.getApiBasePath()}/test-data`, {}, {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data;
    }
}

Shopware.Application.addServiceProvider('externalOrderService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    const httpClient = initContainer?.httpClient ?? container.httpClient;
    const loginService = container.loginService ?? Shopware.Service('loginService');

    return new ExternalOrderService(httpClient, loginService);
});
