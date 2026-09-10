<?php

namespace Tests\Feature\Fiscal;

use App\Exceptions\FiscalizationException;
use App\Services\Fiscal\FiskalyErrorTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Nothing fiskaly says reaches a restaurant in fiskaly's own words.
 *
 * Every case here starts from a real rejection body and asserts two things:
 * the reader is told which field to retype, and no error code or JSON path
 * survives into what they read.
 */
class FiskalyErrorTranslationTest extends TestCase
{
    /** @param array<string, mixed> $body */
    private function rejection(array $body, int $status = 400, string $path = '/signature-creation-unit/x'): FiscalizationException
    {
        return new FiscalizationException(
            'fiskaly rejected the request with HTTP '.$status.'.',
            ['method' => 'PUT', 'path' => $path, 'status' => $status, 'body' => $body],
        );
    }

    private function schema(string $message): FiscalizationException
    {
        return $this->rejection(['code' => 'E_FAILED_SCHEMA_VALIDATION', 'message' => $message]);
    }

    /** No line anywhere may leak the provider's vocabulary. */
    private function assertReadable(array $lines): void
    {
        foreach ($lines as $line) {
            foreach (['body.', 'E_', 'oneOf', 'anyOf', 'should NOT', 'schema'] as $jargon) {
                $this->assertStringNotContainsString(
                    $jargon,
                    $line,
                    'A vendor was shown fiskaly\'s own wording: '.$line,
                );
            }

            $this->assertMatchesRegularExpression('/[.!]$/', $line, 'Not a sentence: '.$line);
        }
    }

    /**
     * The rejection from the bug report. fiskaly answers a single bad VAT
     * number with four clauses — the pattern that failed plus the two
     * alternatives it would have taken instead — and only the first is a thing
     * anyone can act on.
     */
    public function test_a_malformed_austrian_vat_number_names_the_format_it_wants(): void
    {
        $lines = FiskalyErrorTranslator::lines($this->schema(
            'body.legal_entity_id.vat_id should match pattern "^ATU\d{8}$", '
            ."body.legal_entity_id should have required property 'tax_id', "
            ."body.legal_entity_id should have required property 'gln', "
            .'body.legal_entity_id should match exactly one schema in oneOf'
        ));

        $this->assertReadable($lines);
        $this->assertStringContainsString('VAT number', $lines[0]);
        $this->assertStringContainsString('ATU', $lines[0]);
        $this->assertStringContainsString('8 digits', $lines[0]);
        $this->assertStringContainsString('ATU12345678', $lines[0]);

        // The alternatives are offered, not demanded — the vendor has not
        // failed to supply a GLN, they supplied a malformed VAT number.
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('would be accepted instead', $lines[1]);
    }

    /** Nothing supplied at all really is a choice of three. */
    public function test_a_missing_tax_identifier_is_offered_as_a_choice(): void
    {
        $lines = FiskalyErrorTranslator::lines($this->schema(
            "body.legal_entity_id should have required property 'vat_id', "
            ."body.legal_entity_id should have required property 'tax_id', "
            ."body.legal_entity_id should have required property 'gln', "
            .'body.legal_entity_id should match exactly one schema in oneOf'
        ));

        $this->assertReadable($lines);
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('any one of them', $lines[0]);
        $this->assertStringContainsString('VAT number', $lines[0]);
    }

    /** Several bad fields come back as several lines, in order. */
    public function test_every_rejected_field_gets_its_own_line(): void
    {
        $lines = FiskalyErrorTranslator::lines($this->schema(
            'body.fon_participant_id should NOT be longer than 12 characters, '
            .'body.fon_user_pin should NOT be shorter than 8 characters, '
            ."body should have required property 'description'"
        ));

        $this->assertReadable($lines);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('Teilnehmer-Identifikation', $lines[0]);
        $this->assertStringContainsString('at most 12 characters', $lines[0]);
        $this->assertStringContainsString('FinanzOnline PIN', $lines[1]);
        $this->assertStringContainsString('at least 8 characters', $lines[1]);
        $this->assertStringContainsString('Cash register name', $lines[2]);
    }

    /**
     * A required property outside a oneOf is genuinely required — the earlier
     * grouping rule must not turn it into an optional alternative.
     */
    public function test_a_plain_missing_field_is_reported_as_required(): void
    {
        $lines = FiskalyErrorTranslator::lines($this->schema(
            "body.address should have required property 'zip', "
            .'body.address.city should NOT be shorter than 1 characters'
        ));

        $this->assertReadable($lines);
        $this->assertSame('Postal code is required.', $lines[0]);
        $this->assertStringContainsString('1 character.', $lines[1]);
    }

    /** @return list<array{0: string, 1: string}> */
    public static function providerCodes(): array
    {
        return [
            ['E_SCU_LIMIT_REACHED', 'signature units'],
            ['E_FON_SESSION_LIMIT_REACHED', 'too many open sessions'],
            ['E_FON_AUTHENTICATION_FAILED', 'FinanzOnline rejected the sign-in details'],
            ['E_FON_CONNECTION_FAILED', 'could not be reached'],
            ['E_VAT_ID_ALREADY_IN_USE', 'already registered to another cash register'],
            ['E_INVALID_STATE_TRANSITION', 'not in a state that allows this step'],
            ['E_RESOURCE_ALREADY_EXISTS', 'already been registered'],
            ['E_ACCESS_DENIED', 'not allowed to perform this step'],
            ['E_TOO_MANY_REQUESTS', 'Wait a minute'],
        ];
    }

    #[DataProvider('providerCodes')]
    public function test_each_provider_code_has_words_of_its_own(string $code, string $expected): void
    {
        $lines = FiskalyErrorTranslator::lines($this->rejection(['code' => $code, 'message' => 'x']));

        $this->assertReadable($lines);
        $this->assertStringContainsString($expected, implode(' ', $lines));
    }

    /** A code nobody has seen before still has to say something useful. */
    public function test_an_unknown_code_falls_back_to_what_the_status_means(): void
    {
        $this->assertReadable(FiskalyErrorTranslator::lines(
            $this->rejection(['code' => 'E_BRAND_NEW', 'message' => 'nope'], 409)
        ));

        $lines = FiskalyErrorTranslator::lines($this->rejection([], 503));
        $this->assertReadable($lines);
        $this->assertStringContainsString('try again shortly', $lines[0]);
    }

    /** Failures we raise before fiskaly is ever called. */
    public function test_our_own_preflight_failures_are_readable_too(): void
    {
        $cases = [
            new FiscalizationException(
                'FinanzOnline web-service credentials are required to provision an Austrian vendor.',
                ['missing' => 'fon_user_pin'],
            ),
            new FiscalizationException('The vendor has no VAT number to register with.'),
            new FiscalizationException('fiskaly credentials are not configured.'),
            new FiscalizationException('fiskaly request failed: cURL error 28'),
            new FiscalizationException('No fiskaly provider is configured for this country.'),
            new RuntimeException('undefined array key "foo"'),
        ];

        foreach ($cases as $case) {
            $lines = FiskalyErrorTranslator::lines($case);

            $this->assertNotEmpty($lines);
            $this->assertReadable($lines);
        }
    }

    /** The missing value is named, not just "the details are incomplete". */
    public function test_a_missing_finanzonline_value_says_which_one(): void
    {
        $lines = FiskalyErrorTranslator::lines(new FiscalizationException(
            'FinanzOnline web-service credentials are required to provision an Austrian vendor.',
            ['missing' => 'fon_participant_id'],
        ));

        $this->assertStringContainsString('Teilnehmer-Identifikation', $lines[0]);
    }

    /** The jargon has to survive somewhere — just not where a vendor reads it. */
    public function test_the_raw_wording_is_kept_for_the_admin(): void
    {
        $exception = $this->schema('body.legal_entity_id.vat_id should match pattern "^ATU\d{8}$"');
        $detail = FiskalyErrorTranslator::technical($exception);

        $this->assertStringContainsString('E_FAILED_SCHEMA_VALIDATION', $detail);
        $this->assertStringContainsString('body.legal_entity_id.vat_id', $detail);
        $this->assertStringContainsString('HTTP 400', $detail);
    }

    /** text() is what gets stored: the same lines, newline separated. */
    public function test_the_stored_form_keeps_one_reason_per_line(): void
    {
        $exception = $this->schema(
            'body.fon_user_pin should NOT be shorter than 8 characters, '
            .'body.fon_participant_id should NOT be longer than 12 characters'
        );

        $this->assertSame(
            FiskalyErrorTranslator::lines($exception),
            explode("\n", FiskalyErrorTranslator::text($exception)),
        );
    }
}
