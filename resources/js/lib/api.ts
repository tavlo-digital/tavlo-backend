import axios from 'axios';

const CUSTOMER_TOKEN_KEY = 'customer_token';
const VENDOR_TOKEN_KEY = 'vendor_token';

// ----------------------------------------------------------------
// Token helpers
// ----------------------------------------------------------------
export const customerToken = {
    get: () => localStorage.getItem(CUSTOMER_TOKEN_KEY),
    set: (token: string) => localStorage.setItem(CUSTOMER_TOKEN_KEY, token),
    clear: () => localStorage.removeItem(CUSTOMER_TOKEN_KEY),
};

export const vendorToken = {
    get: () => localStorage.getItem(VENDOR_TOKEN_KEY),
    set: (token: string) => localStorage.setItem(VENDOR_TOKEN_KEY, token),
    clear: () => localStorage.removeItem(VENDOR_TOKEN_KEY),
};

// ----------------------------------------------------------------
// Axios instances
// ----------------------------------------------------------------
function createApiInstance(tokenGetter: () => string | null) {
    const instance = axios.create({
        baseURL: '/api',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    });

    instance.interceptors.request.use((config) => {
        const token = tokenGetter();
        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }
        return config;
    });

    return instance;
}

export const customerApi = createApiInstance(customerToken.get);
export const vendorApi = createApiInstance(vendorToken.get);
