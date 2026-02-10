const { ApiService } = Shopware.Classes;

class LieferzeitenOrdersService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'lieferzeiten');
        this.name = 'lieferzeitenOrdersService';
    }

    async getOrders() {
        const response = await this.httpClient.get('/api/_action/lieferzeiten/orders', {
            headers: this.getBasicHeaders(),
        });

        const data = ApiService.handleResponse(response) ?? response?.data;
        return data?.data ?? data ?? [];
    }
}

Shopware.Application.addServiceProvider('lieferzeitenOrdersService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    const httpClient = initContainer?.httpClient ?? container.httpClient;
    const loginService = container.loginService ?? Shopware.Service('loginService');

    return new LieferzeitenOrdersService(httpClient, loginService);
});
