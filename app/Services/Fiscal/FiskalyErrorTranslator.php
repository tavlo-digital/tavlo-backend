<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a fiskaly failure into sentences a restaurant owner can act on.
 *
 * fiskaly answers in JSON-schema violations written for integrators —
 * "body.legal_entity_id.vat_id should match pattern "^ATU\d{8}$", ...
 * body.legal_entity_id should match exactly one schema in oneOf". Put that in
 * front of a restaurant and it is noise; put it in front of the admin chasing
 * them and it still does not say which box to retype.
 *
 * Every path through here ends in plain English. The provider's own wording is
 * kept separately by technical(), for the admin surface and for support tickets
 * with fiskaly, and is never the thing a vendor is shown.
 *
 * One issue per line: a rejection usually carries several, and a single run-on
 * sentence hides all but the first.
 */
final class FiskalyErrorTranslator
{
    /**
     * fiskaly's field paths, in the words the vendor sees on their own form.
     */
    private const FIELDS = [
        'legal_entity_id' => 'company tax identification',
        'legal_entity_id.vat_id' => 'VAT number',
        'legal_entity_id.tax_id' => 'tax number (Steuernummer)',
        'legal_entity_id.gln' => 'GLN (global location number)',
        'vat_id' => 'VAT number',
        'tax_id' => 'tax number (Steuernummer)',
        'gln' => 'GLN (global location number)',

        'fon_participant_id' => 'FinanzOnline participant ID (Teilnehmer-Identifikation)',
        'fon_user_id' => 'FinanzOnline user ID (Benutzer-Identifikation)',
        'fon_user_pin' => 'FinanzOnline PIN',

        'name' => 'legal company name',
        'description' => 'cash register name',
        'state' => 'cash register status',
        'address' => 'legal address',
        'address.street' => 'street address',
        'address.zip' => 'postal code',
        'zip' => 'postal code',
        'address.city' => 'city',
        'city' => 'city',
        'address.country_code' => 'country',
        'country_code' => 'country',
        'town' => 'city',
        'managed_by_organization_id' => 'fiskaly organisation link',
        'organization_id' => 'fiskaly organisation link',

        'receipt_type' => 'receipt type',
        'schema' => 'receipt contents',
        'schema.standard_v1' => 'receipt contents',
        'schema.standard_v1.line_items' => 'receipt line items',
        'schema.standard_v1.amounts_per_vat_rate' => 'receipt VAT breakdown',
        'schema.standard_v1.amounts_per_payment_type' => 'receipt payment breakdown',
        'amounts_per_vat_rate' => 'receipt VAT breakdown',
        'amounts_per_payment_type' => 'receipt payment breakdown',
        'line_items' => 'receipt line items',
    ];

    /**
     * Formats fiskaly enforces, written out rather than left as a regex.
     */
    private const PATTERNS = [
        '^ATU\d{8}$' => 'must be the letters "ATU" followed by exactly 8 digits — for example ATU12345678',
        '^ATU[0-9]{8}$' => 'must be the letters "ATU" followed by exactly 8 digits — for example ATU12345678',
        '^DE\d{9}$' => 'must be the letters "DE" followed by exactly 9 digits — for example DE123456789',
        '^DE[0-9]{9}$' => 'must be the letters "DE" followed by exactly 9 digits — for example DE123456789',
        '^[0-9]{13}$' => 'must be exactly 13 digits',
        '^\d{13}$' => 'must be exactly 13 digits',
    ];

    /**
     * fiskaly's error codes. The key is matched as a prefix, so the whole
     * E_FON_* family is covered even when fiskaly adds a new one.
     *
     * @var array<string, string>
     */
    private const CODES = [
        'E_FON_SESSION_LIMIT' => 'FinanzOnline has too many open sessions for these credentials. Wait a few minutes and try again — nothing needs changing.',
        'E_FON_AUTHENTICATION' => 'FinanzOnline rejected the sign-in details. Check the participant ID, user ID and PIN, and that the web-service user is still active.',
        'E_FON_INVALID_CREDENTIALS' => 'FinanzOnline rejected the sign-in details. Check the participant ID, user ID and PIN, and that the web-service user is still active.',
        'E_FON_CONNECTION' => 'FinanzOnline could not be reached. This is a tax-office outage, not a problem with the details — try again shortly.',
        'E_FON_TIMEOUT' => 'FinanzOnline did not answer in time. Try again shortly; nothing needs changing.',
        'E_FON_UNAVAILABLE' => 'FinanzOnline is unavailable right now. Try again shortly; nothing needs changing.',
        'E_FON' => 'FinanzOnline rejected the registration. Check the FinanzOnline participant ID, user ID and PIN.',

        'E_SCU_LIMIT_REACHED' => 'The fiskaly account has no free signature units left. A Tavlo admin needs to free one up or raise the plan limit before this register can be created.',
        'E_SCU_NOT_INITIALIZED' => 'The signature unit is not ready yet. Retry the registration in a moment.',
        'E_SCU_DEACTIVATED' => 'The signature unit has been deactivated at fiskaly and cannot be used. A Tavlo admin needs to create a new one.',
        'E_CASH_REGISTER_NOT_INITIALIZED' => 'The cash register has not finished starting up. Retry the registration in a moment.',
        'E_CASH_REGISTER_DEACTIVATED' => 'This cash register has been deactivated and cannot sign receipts. A new one has to be registered.',

        'E_VAT_ID_ALREADY' => 'This VAT number is already registered to another cash register at fiskaly. Check the VAT number, or contact Tavlo support to release the old registration.',
        'E_INVALID_VAT_ID' => 'The tax office does not recognise this VAT number. Check it against the VAT certificate and submit again.',
        'E_DUPLICATE' => 'This has already been registered at fiskaly. No further action is needed — retry to pick up the existing registration.',
        'E_RESOURCE_ALREADY_EXISTS' => 'This has already been registered at fiskaly. No further action is needed — retry to pick up the existing registration.',
        'E_CONFLICT' => 'fiskaly already holds a different version of this registration. Retry once; if it repeats, Tavlo support needs to look at it.',

        'E_INVALID_STATE_TRANSITION' => 'The cash register is not in a state that allows this step. Retry the registration from the start.',
        'E_STATE_TRANSITION' => 'The cash register is not in a state that allows this step. Retry the registration from the start.',

        'E_UNAUTHENTICATED' => 'Tavlo could not sign in to fiskaly. This is a Tavlo configuration problem — nothing for the restaurant to change.',
        'E_UNAUTHORIZED' => 'Tavlo could not sign in to fiskaly. This is a Tavlo configuration problem — nothing for the restaurant to change.',
        'E_ACCESS_DENIED' => 'The fiskaly account is not allowed to perform this step. A Tavlo admin needs to check the account permissions.',
        'E_FORBIDDEN' => 'The fiskaly account is not allowed to perform this step. A Tavlo admin needs to check the account permissions.',
        'E_PAYMENT_REQUIRED' => 'The fiskaly account is not paid up, so new registrations are blocked. A Tavlo admin needs to sort the billing.',
        'E_SUBSCRIPTION' => 'The fiskaly plan does not cover this registration. A Tavlo admin needs to check the subscription.',

        'E_RESOURCE_NOT_FOUND' => 'fiskaly has no record of this cash register. Retry the registration to create it again.',
        'E_NOT_FOUND' => 'fiskaly has no record of this cash register. Retry the registration to create it again.',

        'E_TOO_MANY_REQUESTS' => 'Too many requests were sent to fiskaly at once. Wait a minute and try again — nothing needs changing.',
        'E_RATE_LIMIT' => 'Too many requests were sent to fiskaly at once. Wait a minute and try again — nothing needs changing.',

        'E_INTERNAL' => 'fiskaly hit a problem on their side. Nothing is wrong with the details — try again shortly.',
        'E_SERVICE_UNAVAILABLE' => 'fiskaly is temporarily unavailable. Nothing is wrong with the details — try again shortly.',
    ];

    /**
     * Plain-English reasons, one per line, never empty.
     *
     * @return list<string>
     */
    public static function lines(Throwable $exception): array
    {
        $summary = $exception instanceof FiscalizationException
            ? $exception->summary
            : $exception->getMessage();

        // Problems we spot ourselves, before fiskaly is ever called.
        if ($ours = self::preflight($summary, $exception)) {
            return $ours;
        }

        if (! $exception instanceof FiscalizationException) {
            return ['The cash register could not be registered. Tavlo support needs to look at this one.'];
        }

        $body = $exception->context['body'] ?? null;
        $body = is_array($body) ? $body : [];
        $code = self::stringField($body, ['code', 'error_code']);
        $message = self::stringField($body, ['message', 'detail', 'error_description', 'error']);
        $status = $exception->context['status'] ?? null;

        // The schema violations are the ones worth spelling out field by field.
        if ($code === 'E_FAILED_SCHEMA_VALIDATION' || str_contains($message, ' should ')) {
            $fields = self::schemaLines($message);

            if ($fields !== []) {
                return $fields;
            }
        }

        if ($code !== '' && $known = self::byCode($code)) {
            return [$known];
        }

        // No code we recognise: fall back on what the HTTP status means.
        return [self::byStatus(is_int($status) ? $status : null)];
    }

    /** Plain English as a single block — one issue per line. */
    public static function text(Throwable $exception): string
    {
        return implode("\n", self::lines($exception));
    }

    /**
     * fiskaly's own words, for the admin surface and for support tickets.
     * Never shown to a vendor.
     */
    public static function technical(Throwable $exception): ?string
    {
        if (! $exception instanceof FiscalizationException) {
            return Str::limit($exception->getMessage(), 500) ?: null;
        }

        $parts = array_filter([
            $exception->providerDetail(),
            isset($exception->context['status']) ? 'HTTP '.$exception->context['status'] : null,
            isset($exception->context['path']) ? (string) $exception->context['path'] : null,
        ]);

        return $parts ? Str::limit(implode(' · ', $parts), 500) : null;
    }

    /**
     * Failures raised on our side of the call, matched on the summary the
     * thrower wrote.
     *
     * @return list<string>|null
     */
    private static function preflight(string $summary, Throwable $exception): ?array
    {
        $missing = $exception instanceof FiscalizationException
            ? (string) ($exception->context['missing'] ?? '')
            : '';

        return match (true) {
            str_contains($summary, 'FinanzOnline web-service credentials are required') => [
                match ($missing) {
                    'fon_participant_id' => 'The FinanzOnline participant ID (Teilnehmer-Identifikation) is missing. Add it and submit again.',
                    'fon_user_id' => 'The FinanzOnline user ID (Benutzer-Identifikation) is missing. Add it and submit again.',
                    'fon_user_pin' => 'The FinanzOnline PIN is missing. Add it and submit again.',
                    default => 'The FinanzOnline details are incomplete — the participant ID, user ID and PIN are all needed.',
                },
            ],

            str_contains($summary, 'no VAT number') => [
                'A VAT number is required to register a cash register in Austria. Add it to the legal and tax details, then submit again.',
            ],

            str_contains($summary, 'legal name, address, postal code') => [
                'The legal company name, legal address, postal code and city are all required before a cash register can be registered.',
            ],

            str_contains($summary, 'credentials are not configured') => [
                'Tavlo has no fiskaly API credentials on this environment, so no cash register can be registered.',
                'This is a Tavlo setup problem — nothing for the restaurant to change.',
            ],

            str_contains($summary, 'empty access token') => [
                'fiskaly returned an empty sign-in token. This is a fiskaly-side fault — try again shortly.',
            ],

            str_contains($summary, 'authentication failed') => [
                'Tavlo could not sign in to fiskaly, so the registration never reached the tax office.',
                'This is a Tavlo setup problem — nothing for the restaurant to change.',
            ],

            str_contains($summary, 'one-time secret is unavailable') => [
                'The one-time fiskaly key for this restaurant is no longer available, so the register cannot be set up on the existing account.',
                'Tavlo support needs to create a fresh fiskaly organisation for this restaurant.',
            ],

            str_contains($summary, 'No fiskaly provider is configured'),
            str_contains($summary, 'No fiskaly base URL') => [
                'Tavlo does not support registering a cash register in this country yet.',
            ],

            str_contains($summary, 'not a FiscalProvider') => [
                'The cash register service is misconfigured for this country. This is a Tavlo setup problem.',
            ],

            str_contains($summary, 'no initialized fiscal device') => [
                'This restaurant has no registered cash register yet, so receipts cannot be signed.',
                'Register the cash register first, then retry.',
            ],

            str_contains($summary, 'fiskaly request failed') => [
                'fiskaly could not be reached — the connection failed or timed out.',
                'Nothing is wrong with the details. Try again shortly.',
            ],

            default => null,
        };
    }

    /**
     * Splits a JSON-schema rejection into one plain sentence per real problem.
     *
     * fiskaly returns every branch it tried, so a single wrong VAT number comes
     * back as four clauses: the pattern that failed, the two alternative
     * properties it would have accepted instead, and a "oneOf" summary. Only
     * the first is a thing anyone can fix.
     *
     * The "oneOf" clause is what makes that distinction safe — it names the
     * field whose sub-properties are alternatives. Without it, a missing
     * property is simply missing.
     *
     * @return list<string>
     */
    private static function schemaLines(string $message): array
    {
        $clauses = self::clauses($message);

        if ($clauses === []) {
            return [];
        }

        // Fields whose children are alternatives rather than a checklist.
        $eitherOr = [];
        // Fields where something concrete already failed (a pattern, a length).
        $failed = [];

        foreach ($clauses as [$path, $rule]) {
            if (self::isChoiceRule($rule)) {
                $eitherOr[$path] = true;

                continue;
            }

            if (! str_starts_with($rule, 'have required property')) {
                $failed[self::parentOf($path)] = true;
            }
        }

        $lines = [];
        $choices = [];

        foreach ($clauses as [$path, $rule]) {
            // "should match exactly one schema in oneOf" restates the clauses
            // above it and names no field of its own.
            if (self::isMetaRule($rule)) {
                continue;
            }

            if (preg_match("/^have required property '?([^'\"]+)'?/", $rule, $m)) {
                $child = trim($path.'.'.$m[1], '.');

                if (isset($eitherOr[$path])) {
                    $choices[$path][] = self::labelFor($child);

                    continue;
                }

                $lines[] = Str::ucfirst(self::labelFor($child)).' is required.';

                continue;
            }

            $lines[] = self::describe($path, $rule);
        }

        foreach ($choices as $parent => $labels) {
            $labels = array_values(array_unique($labels));

            if ($labels === []) {
                continue;
            }

            // Something in this group already failed on its own merits, so the
            // rest are only the ways out of it, not extra demands.
            $lines[] = isset($failed[$parent])
                ? Str::ucfirst(self::orList($labels)).' would be accepted instead.'
                : Str::ucfirst(self::orList($labels)).' is required — any one of them.';
        }

        return array_values(array_unique(array_filter($lines)));
    }

    /**
     * Splits fiskaly's comma-run into "path should rule" pairs.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function clauses(string $message): array
    {
        $parts = preg_split(
            "/,\s*(?=[A-Za-z_][\w.\[\]']*\s+should\s)/",
            trim($message),
        ) ?: [];

        $clauses = [];

        foreach ($parts as $part) {
            if (preg_match('/^(?<path>\S+)\s+should\s+(?<rule>.+)$/s', trim($part), $m)) {
                $clauses[] = [self::normalisePath($m['path']), trim($m['rule'])];
            }
        }

        return $clauses;
    }

    private static function isMetaRule(string $rule): bool
    {
        foreach (['schema in oneOf', 'schema in anyOf', 'schema in allOf', 'NOT be valid'] as $meta) {
            if (str_contains($rule, $meta)) {
                return true;
            }
        }

        return false;
    }

    /** "should match exactly one schema in oneOf" — the alternatives marker. */
    private static function isChoiceRule(string $rule): bool
    {
        return str_contains($rule, 'schema in oneOf') || str_contains($rule, 'schema in anyOf');
    }

    /** One schema rule, said out loud. */
    private static function describe(string $path, string $rule): string
    {
        $label = self::labelFor($path);
        $Label = Str::ucfirst($label);

        if (preg_match('/^match pattern "(.+)"$/', $rule, $m)) {
            $pattern = stripcslashes($m[1]);

            return isset(self::PATTERNS[$m[1]]) || isset(self::PATTERNS[$pattern])
                ? $Label.' '.(self::PATTERNS[$m[1]] ?? self::PATTERNS[$pattern]).'.'
                : $Label.' is not in the format the tax office expects. Check it for stray spaces, '
                    .'dashes or a missing country prefix.';
        }

        return match (true) {
            (bool) preg_match('/^NOT be shorter than (\d+) character/', $rule, $m) => $Label.' is too short — it needs at least '.$m[1].' '.Str::plural('character', (int) $m[1]).'.',

            (bool) preg_match('/^NOT be longer than (\d+) character/', $rule, $m) => $Label.' is too long — it can be at most '.$m[1].' '.Str::plural('character', (int) $m[1]).'.',

            (bool) preg_match('/^NOT have fewer than (\d+) item/', $rule, $m) => $Label.' needs at least '.$m[1].' '.Str::plural('entry', (int) $m[1]).'.',

            (bool) preg_match('/^NOT have more than (\d+) item/', $rule, $m) => $Label.' has too many entries — at most '.$m[1].' are allowed.',

            (bool) preg_match('/^NOT be less than (\S+)/', $rule, $m) => $Label.' cannot be lower than '.$m[1].'.',

            (bool) preg_match('/^NOT be greater than (\S+)/', $rule, $m) => $Label.' cannot be higher than '.$m[1].'.',

            (bool) preg_match('/^match format "([^"]+)"/', $rule, $m) => $m[1] === 'email'
                    ? $Label.' must be a valid email address.'
                    : $Label.' is not in the format the tax office expects.',

            str_starts_with($rule, 'be equal to one of the allowed values') => $Label.' is not one of the values the tax office accepts.',

            (bool) preg_match('/^be (a |an )?(string|number|integer|boolean|array|object)/', $rule, $m) => $Label.' was sent in the wrong format. Tavlo support needs to look at this one.',

            str_starts_with($rule, 'NOT have additional properties') => 'Tavlo sent a field the tax office does not accept. Tavlo support needs to look at this one.',

            default => $Label.' was rejected by the tax office.',
        };
    }

    private static function labelFor(string $path): string
    {
        if (isset(self::FIELDS[$path])) {
            return self::FIELDS[$path];
        }

        // Try progressively shorter tails: address.zip, then zip.
        $segments = explode('.', $path);

        while (count($segments) > 1) {
            array_shift($segments);
            $tail = implode('.', $segments);

            if (isset(self::FIELDS[$tail])) {
                return self::FIELDS[$tail];
            }
        }

        return '"'.str_replace('_', ' ', (string) array_pop($segments)).'"';
    }

    private static function parentOf(string $path): string
    {
        $at = strrpos($path, '.');

        return $at === false ? '' : substr($path, 0, $at);
    }

    /** Drops fiskaly's "body." / "data." wrapper and any array indexes. */
    private static function normalisePath(string $path): string
    {
        $path = preg_replace('/^(body|data|payload)\./', '', $path) ?? $path;

        return preg_replace('/\[\d+\]/', '', $path) ?? $path;
    }

    /** @param list<string> $items */
    private static function orList(array $items): string
    {
        $items = array_values(array_unique($items));

        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return 'a '.$items[0];
        }

        $last = array_pop($items);

        return 'a '.implode(', a ', $items).' or a '.$last;
    }

    private static function byCode(string $code): ?string
    {
        $code = strtoupper($code);

        // Longest prefix first, so E_FON_TIMEOUT beats the E_FON catch-all.
        $codes = self::CODES;
        uksort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codes as $prefix => $sentence) {
            if (str_starts_with($code, $prefix)) {
                return $sentence;
            }
        }

        return null;
    }

    private static function byStatus(?int $status): string
    {
        return match (true) {
            $status === 400 || $status === 422 => 'The tax office rejected the details as invalid. Check the VAT number and the FinanzOnline participant ID, user ID and PIN, then submit again.',
            $status === 401 || $status === 403 => 'Tavlo is not allowed to register this cash register at fiskaly. This is a Tavlo setup problem — nothing for the restaurant to change.',
            $status === 404 => 'fiskaly has no record of this cash register. Retry the registration to create it again.',
            $status === 409 => 'This registration clashes with one fiskaly already holds. Retry once; if it repeats, Tavlo support needs to look at it.',
            $status === 429 => 'Too many requests were sent to fiskaly at once. Wait a minute and try again — nothing needs changing.',
            is_int($status) && $status >= 500 => 'fiskaly is having problems on their side. Nothing is wrong with the details — try again shortly.',
            default => 'The cash register could not be registered. Tavlo support needs to look at this one.',
        };
    }

    /** @param array<string, mixed> $body */
    private static function stringField(array $body, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $body[$key] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }
}
