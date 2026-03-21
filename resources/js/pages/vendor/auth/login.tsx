import { useState } from 'react';
import VendorAuthLayout from '@/layouts/vendor-auth-layout';
import { useVendorAuth } from '@/hooks/use-vendor-auth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function VendorLoginPage() {
    const { login, loading, errors } = useVendorAuth();

    const [form, setForm] = useState({ email: '', password: '' });

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await login(form);
            window.location.href = '/vendor/dashboard';
        } catch {
            // errors displayed via state
        }
    };

    const field = (key: keyof typeof form) =>
        errors[key] ? (
            <p className="text-xs text-destructive">{errors[key][0]}</p>
        ) : null;

    return (
        <VendorAuthLayout title="Vendor sign in" description="Access your vendor dashboard">
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                {/* Email */}
                <div className="grid gap-1">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        value={form.email}
                        onChange={handleChange}
                        required
                    />
                    {field('email')}
                </div>

                {/* Password */}
                <div className="grid gap-1">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        autoComplete="current-password"
                        value={form.password}
                        onChange={handleChange}
                        required
                    />
                    {field('password')}
                </div>

                <Button type="submit" disabled={loading} className="w-full">
                    {loading ? 'Signing in…' : 'Sign in'}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Don&apos;t have an account?{' '}
                    <a href="/vendor/register" className="underline underline-offset-4">
                        Create one
                    </a>
                </p>
            </form>
        </VendorAuthLayout>
    );
}
