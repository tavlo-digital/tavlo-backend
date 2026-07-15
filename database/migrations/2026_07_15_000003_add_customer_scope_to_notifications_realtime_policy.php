<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS notifications_realtime_actor_read ON public.notifications');
        DB::statement(<<<'SQL'
            CREATE POLICY notifications_realtime_actor_read
            ON public.notifications
            FOR SELECT
            TO authenticated
            USING (
                (
                    (auth.jwt() ->> 'actor_type') = 'customer'
                    AND customer_id = ((auth.jwt() ->> 'actor_id')::bigint)
                )
                OR (
                    (auth.jwt() ->> 'actor_type') = 'vendor'
                    AND vendor_id = ((auth.jwt() ->> 'actor_id')::bigint)
                    AND customer_id IS NULL
                    AND waiter_id IS NULL
                    AND kitchen_id IS NULL
                )
                OR (
                    (auth.jwt() ->> 'actor_type') = 'team_member'
                    AND (auth.jwt() ->> 'actor_role') = 'waiter'
                    AND waiter_id = ((auth.jwt() ->> 'actor_id')::bigint)
                )
                OR (
                    (auth.jwt() ->> 'actor_type') = 'team_member'
                    AND (auth.jwt() ->> 'actor_role') = 'kitchen'
                    AND kitchen_id = ((auth.jwt() ->> 'actor_id')::bigint)
                )
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS notifications_realtime_actor_read ON public.notifications');
        DB::statement(<<<'SQL'
            CREATE POLICY notifications_realtime_actor_read
            ON public.notifications
            FOR SELECT
            TO authenticated
            USING (
                (
                    (auth.jwt() ->> 'actor_type') = 'vendor'
                    AND vendor_id = ((auth.jwt() ->> 'actor_id')::bigint)
                    AND customer_id IS NULL
                    AND waiter_id IS NULL
                    AND kitchen_id IS NULL
                )
                OR (
                    (auth.jwt() ->> 'actor_type') = 'team_member'
                    AND (auth.jwt() ->> 'actor_role') = 'waiter'
                    AND waiter_id = ((auth.jwt() ->> 'actor_id')::bigint)
                )
                OR (
                    (auth.jwt() ->> 'actor_type') = 'team_member'
                    AND (auth.jwt() ->> 'actor_role') = 'kitchen'
                    AND kitchen_id = ((auth.jwt() ->> 'actor_id')::bigint)
                )
            )
        SQL);
    }
};
