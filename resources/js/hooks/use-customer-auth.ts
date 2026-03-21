import { useState } from 'react';
import { customerApi, customerToken } from '@/lib/api';

export interface Customer {
    id: number;
    name: string;
    phone: string;
    email: string;
    created_at: string;
}

interface RegisterData {
    name: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
}

interface LoginData {
    email: string;
    password: string;
}

export function useCustomerAuth() {
    const [user, setUser] = useState<Customer | null>(null);
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [loading, setLoading] = useState(false);

    const register = async (data: RegisterData) => {
        setLoading(true);
        setErrors({});
        try {
            const response = await customerApi.post('/customer/register', data);
            customerToken.set(response.data.token);
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
            const response = await customerApi.post('/customer/login', data);
            customerToken.set(response.data.token);
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
            await customerApi.post('/customer/logout');
        } finally {
            customerToken.clear();
            setUser(null);
            setLoading(false);
        }
    };

    const fetchMe = async () => {
        const token = customerToken.get();
        if (!token) return null;
        try {
            const response = await customerApi.get('/customer/me');
            setUser(response.data);
            return response.data;
        } catch {
            customerToken.clear();
            return null;
        }
    };

    return { user, errors, loading, register, login, logout, fetchMe };
}
