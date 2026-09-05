import { useEffect, useState } from 'react';
import { supabase } from '@/lib/supabase';

export type Notification = {
    id: number;
    customer_id: number | null;
    vendor_id: number | null;
    waiter_id: number | null;
    kitchen_id: number | null;
    admin_id: number | null;
    event: string;
    message: string;
    read: boolean;
    created_at: string;
};

export function useNotifications(role: string, userId: number) {
    const [notifications, setNotifications] = useState<Notification[]>([]);

    useEffect(() => {
        if (!role || !userId) return;

        const column = `${role}_id`;

        const channel = supabase
            .channel(`notifications:${role}:${userId}`)
            .on(
                'postgres_changes',
                {
                    event: 'INSERT',
                    schema: 'public',
                    table: 'notifications',
                    filter: `${column}=eq.${userId}`,
                },
                (payload) => {
                    console.log('[Realtime] notification received:', payload.new);
                    setNotifications((prev) => [payload.new as Notification, ...prev]);
                },
            )
            .subscribe((status, err) => {
                console.log(`[Realtime] status:`, status, err ?? '');
            });

        return () => {
            supabase.removeChannel(channel);
        };
    }, [role, userId]);

    const clearNotifications = () => setNotifications([]);

    return { notifications, clearNotifications };
}
