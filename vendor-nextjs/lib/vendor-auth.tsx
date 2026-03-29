"use client";

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from "react";
import { createVendorApi, vendorToken } from "@/lib/api";
import type { Vendor, AuthResponse } from "@/lib/types";
import { useRouter } from "next/navigation";

interface VendorAuthContextValue {
  user: Vendor | null;
  loading: boolean;
  errors: Record<string, string[]>;
  login: (data: { email: string; password: string }) => Promise<void>;
  register: (data: {
    name: string;
    country: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => Promise<void>;
  logout: () => Promise<void>;
}

const VendorAuthContext = createContext<VendorAuthContextValue | null>(null);

export function VendorAuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<Vendor | null>(null);
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [loading, setLoading] = useState(true);
  const router = useRouter();
  const api = createVendorApi();

  const fetchMe = useCallback(async () => {
    const token = vendorToken.get();
    if (!token) {
      setLoading(false);
      return;
    }
    try {
      const data = await api.get<{ data: Vendor }>("/vendor/me");
      setUser(data.data);
    } catch {
      vendorToken.clear();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchMe();
  }, [fetchMe]);

  const login = async (data: { email: string; password: string }) => {
    setLoading(true);
    setErrors({});
    try {
      const res = await api.post<AuthResponse<Vendor>>(
        "/vendor/login",
        data as unknown as Record<string, unknown>
      );
      vendorToken.set(res.token);
      setUser(res.user);
      router.push("/dashboard");
    } catch (err: unknown) {
      const e = err as { status?: number; data?: { errors?: Record<string, string[]> } };
      if (e.status === 422 && e.data?.errors) {
        setErrors(e.data.errors);
      }
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const register = async (data: {
    name: string;
    country: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) => {
    setLoading(true);
    setErrors({});
    try {
      const res = await api.post<AuthResponse<Vendor>>(
        "/vendor/register",
        data as unknown as Record<string, unknown>
      );
      vendorToken.set(res.token);
      setUser(res.user);
      router.push("/dashboard");
    } catch (err: unknown) {
      const e = err as { status?: number; data?: { errors?: Record<string, string[]> } };
      if (e.status === 422 && e.data?.errors) {
        setErrors(e.data.errors);
      }
      throw err;
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    try {
      await api.post("/vendor/logout");
    } finally {
      vendorToken.clear();
      setUser(null);
      router.push("/login");
    }
  };

  return (
    <VendorAuthContext.Provider
      value={{ user, loading, errors, login, register, logout }}
    >
      {children}
    </VendorAuthContext.Provider>
  );
}

export function useVendorAuth() {
  const ctx = useContext(VendorAuthContext);
  if (!ctx) throw new Error("useVendorAuth must be inside VendorAuthProvider");
  return ctx;
}
