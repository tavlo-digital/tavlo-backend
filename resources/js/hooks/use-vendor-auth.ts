import { useState } from 'react';
import { vendorApi, vendorToken } from '@/lib/api';

export interface Vendor {
    id: number;
    name: string;
    country: string;
    phone: string;
    email: string;
    created_at: string;
}

interface RegisterData {
    name: string;
    country: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
}

interface LoginData {
    email: string;
    password: string;
}

export function useVendorAuth() {
    const [user, setUser] = useState<Vendor | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [loading, setLoading] = useState(false);

    const register = async (data: RegisterData) => {
        setLoading(true);
        setErrors({});
        try {
            const response = await vendorApi.post('/vendor/register', data);
            vendorToken.set(response.data.token);
            setUser(response.data.user);
            return response.data;
        } catch (err: any) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors ?? {});
            }
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const login = async (data: LoginData) => {
        setLoading(true);
        setErrors({});
        try {
            const response = await vendorApi.post('/vendor/login', data);
            vendorToken.set(response.data.token);
            setUser(response.data.user);
            return response.data;
        } catch (err: any) {
            if (err.response?.status === 422) {
                setErrors(err.response.data.errors ?? {});
            }
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const logout = async () => {
        setLoading(true);
        try {
            await vendorApi.post('/vendor/logout');
        } finally {
            vendorToken.clear();
            setUser(null);
            setLoading(false);
        }
    };

    const fetchMe = async () => {
        const token = vendorToken.get();
        if (!token) return null;
        try {
            const response = await vendorApi.get('/vendor/me');
            setUser(response.data);
            return response.data;
        } catch {
            vendorToken.clear();
            return null;
        }
    };

    return { user, errors, loading, register, login, logout, fetchMe };
}
