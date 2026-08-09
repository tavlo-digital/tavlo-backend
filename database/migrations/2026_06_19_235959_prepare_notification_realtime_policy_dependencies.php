<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MARKER = 'tavlo:temporary-notification-realtime-compatibility';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || $this->hasRealtimePolicyDependencies()) {
            return;
        }

        if (! $this->roleExists()) {
            DB::statement('CREATE ROLE authenticated NOLOGIN');
            DB::statement("COMMENT ON ROLE authenticated IS '".self::MARKER."'");
        }

        if (! $this->authSchemaExists()) {
            DB::statement('CREATE SCHEMA auth');
            DB::statement("COMMENT ON SCHEMA auth IS '".self::MARKER."'");
        }

        if (! $this->jwtFunctionExists()) {
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION auth.jwt()
                RETURNS jsonb
                LANGUAGE sql
                STABLE
                AS $function$
                    SELECT '{}'::jsonb
                $function$
            SQL);
            DB::statement("COMMENT ON FUNCTION auth.jwt() IS '".self::MARKER."'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $ownsRole = $this->roleComment() === self::MARKER;
        $ownsFunction = $this->jwtFunctionComment() === self::MARKER;
        $ownsSchema = $this->authSchemaComment() === self::MARKER;

        if (! $ownsRole && ! $ownsFunction && ! $ownsSchema) {
            return;
        }

        if ($this->notificationsTableExists()) {
            DB::statement('DROP POLICY IF EXISTS notifications_realtime_actor_read ON public.notifications');
            DB::statement('ALTER TABLE public.notifications DISABLE ROW LEVEL SECURITY');

            if ($this->roleExists()) {
                DB::statement('REVOKE SELECT ON public.notifications FROM authenticated');
            }
        }

        if ($ownsFunction) {
            DB::statement('DROP FUNCTION auth.jwt()');
        }

        if ($ownsSchema) {
            DB::statement('DROP SCHEMA auth');
        }

        if ($ownsRole) {
            DB::statement('DROP ROLE authenticated');
        }
    }

    private function hasRealtimePolicyDependencies(): bool
    {
        return $this->roleExists() && $this->jwtFunctionExists();
    }

    private function roleExists(): bool
    {
        $result = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') AS present");

        return (bool) ($result->present ?? false);
    }

    private function authSchemaExists(): bool
    {
        $result = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'auth') AS present");

        return (bool) ($result->present ?? false);
    }

    private function jwtFunctionExists(): bool
    {
        $result = DB::selectOne(<<<'SQL'
            SELECT EXISTS (
                SELECT 1
                FROM pg_proc
                INNER JOIN pg_namespace ON pg_namespace.oid = pg_proc.pronamespace
                WHERE pg_namespace.nspname = 'auth'
                  AND pg_proc.proname = 'jwt'
                  AND pg_proc.pronargs = 0
            ) AS present
        SQL);

        return (bool) ($result->present ?? false);
    }

    private function notificationsTableExists(): bool
    {
        $result = DB::selectOne("SELECT to_regclass('public.notifications') IS NOT NULL AS present");

        return (bool) ($result->present ?? false);
    }

    private function roleComment(): ?string
    {
        return DB::selectOne(<<<'SQL'
            SELECT shobj_description(oid, 'pg_authid') AS description
            FROM pg_roles
            WHERE rolname = 'authenticated'
        SQL)?->description;
    }

    private function authSchemaComment(): ?string
    {
        return DB::selectOne(<<<'SQL'
            SELECT obj_description(oid, 'pg_namespace') AS description
            FROM pg_namespace
            WHERE nspname = 'auth'
        SQL)?->description;
    }

    private function jwtFunctionComment(): ?string
    {
        return DB::selectOne(<<<'SQL'
            SELECT obj_description(pg_proc.oid, 'pg_proc') AS description
            FROM pg_proc
            INNER JOIN pg_namespace ON pg_namespace.oid = pg_proc.pronamespace
            WHERE pg_namespace.nspname = 'auth'
              AND pg_proc.proname = 'jwt'
              AND pg_proc.pronargs = 0
            LIMIT 1
        SQL)?->description;
    }
};
