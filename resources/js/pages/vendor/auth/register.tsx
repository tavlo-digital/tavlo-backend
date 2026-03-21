import { useState } from 'react';
import VendorAuthLayout from '@/layouts/vendor-auth-layout';
import { useVendorAuth } from '@/hooks/use-vendor-auth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function VendorRegisterPage() {
    const { register, loading, errors } = useVendorAuth();

    const [form, setForm] = useState({
        name: '',
        country: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        try {
            await register(form);
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
        <VendorAuthLayout title="Become a vendor" description="Join Tavlo and start selling">
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                {/* Name */}
                <div className="grid gap-1">
                    <Label htmlFor="name">Business / Full name</Label>
                    <Input
                        id="name"
                        name="name"
                        autoComplete="name"
                        value={form.name}
                        onChange={handleChange}
                        required
                    />
                    {field('name')}
                </div>

                {/* Country */}
                <div className="grid gap-1">
                    <Label htmlFor="country">Country</Label>
                    <Input
                        id="country"
                        name="country"
                        autoComplete="country-name"
                        value={form.country}
                        onChange={handleChange}
                        required
                    />
                    {field('country')}
                </div>

                {/* Phone */}
                <div className="grid gap-1">
                    <Label htmlFor="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        autoComplete="tel"
                        value={form.phone}
                        onChange={handleChange}
                        required
                    />
                    {field('phone')}
                </div>

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
                        autoComplete="new-password"
                        value={form.password}
                        onChange={handleChange}
                        required
                    />
                    {field('password')}
                </div>

                {/* Confirm password */}
                <div className="grid gap-1">
                    <Label htmlFor="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={form.password_confirmation}
                        onChange={handleChange}
                        required
                    />
                </div>

                <Button type="submit" disabled={loading} className="w-full">
                    {loading ? 'Creating account…' : 'Create vendor account'}
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <a href="/vendor/login" className="underline underline-offset-4">
                        Sign in
                    </a>
                </p>
            </form>
        </VendorAuthLayout>
    );
}
