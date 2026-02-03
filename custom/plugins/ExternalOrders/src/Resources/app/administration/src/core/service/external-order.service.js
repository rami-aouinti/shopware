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

        const response = await this.httpClient.get(`/_action/${this.apiEndpoint}/list`, {
            headers: this.getBasicHeaders(),
            params,
        });

        return ApiService.handleResponse(response);
    }

    async detail(orderId) {
        const response = await this.httpClient.get(`/_action/${this.apiEndpoint}/detail/${orderId}`, {
            headers: this.getBasicHeaders(),
        });

        return ApiService.handleResponse(response);
    }
}

Shopware.Application.addServiceProvider('externalOrderService', (container) => {
    return new ExternalOrderService(container.httpClient, Shopware.Service('loginService'));
});
