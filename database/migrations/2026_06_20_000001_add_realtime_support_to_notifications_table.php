<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->boolean('is_silent')->default(false)->after('read');
            $table->index(['vendor_id', 'is_silent', 'read', 'created_at'], 'notifications_vendor_feed_index');
            $table->index(['waiter_id', 'is_silent', 'read', 'created_at'], 'notifications_waiter_feed_index');
            $table->index(['kitchen_id', 'is_silent', 'read', 'created_at'], 'notifications_kitchen_feed_index');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE public.notifications ENABLE ROW LEVEL SECURITY');
        DB::statement('GRANT SELECT ON public.notifications TO authenticated');
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

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime')
                   AND NOT EXISTS (
                       SELECT 1 FROM pg_publication_tables
                       WHERE pubname = 'supabase_realtime'
                         AND schemaname = 'public'
                         AND tablename = 'notifications'
                   ) THEN
                    EXECUTE 'ALTER PUBLICATION supabase_realtime ADD TABLE public.notifications';
                END IF;
            END $$
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP POLICY IF EXISTS notifications_realtime_actor_read ON public.notifications');
            DB::statement('ALTER TABLE public.notifications DISABLE ROW LEVEL SECURITY');
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_vendor_feed_index');
            $table->dropIndex('notifications_waiter_feed_index');
            $table->dropIndex('notifications_kitchen_feed_index');
            $table->dropColumn('is_silent');
        });
    }
};
