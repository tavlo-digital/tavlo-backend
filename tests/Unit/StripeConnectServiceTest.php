<?php

namespace Tests\Unit;

use App\Services\StripeConnectService;
use Closure;
use ErrorException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StripeConnectServiceTest extends TestCase
{
    public function test_accounts_v2_advisory_does_not_turn_a_successful_stripe_response_into_an_exception(): void
    {
        Log::shouldReceive('notice')
            ->once()
            ->with('Stripe API advisory received.', [
                'message' => 'We recommend building your integration using Accounts v2. See https://docs.stripe.com/api/v2/core/accounts',
            ]);

        $result = (new TestableStripeConnectService)->runStripeRequest(function (): string {
            trigger_error(
                'We recommend building your integration using Accounts v2. See https://docs.stripe.com/api/v2/core/accounts',
                E_USER_WARNING,
            );

            return 'acct_success';
        });

        $this->assertSame('acct_success', $result);
    }

    public function test_unrelated_stripe_warnings_still_use_laravels_error_handler(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('A real Stripe SDK warning.');

        (new TestableStripeConnectService)->runStripeRequest(function (): void {
            trigger_error('A real Stripe SDK warning.', E_USER_WARNING);
        });
    }
}

class TestableStripeConnectService extends StripeConnectService
{
    public function runStripeRequest(Closure $request): mixed
    {
        return $this->stripeRequest($request);
    }
}
