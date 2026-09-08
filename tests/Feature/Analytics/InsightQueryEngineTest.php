<?php

namespace Tests\Feature\Analytics;

use App\Services\Analytics\InsightEngine;
use App\Services\Analytics\InsightQueryEngine;
use Tests\TestCase;

/**
 * Unit-style coverage for the queryable half of the assistant, mirroring how
 * VendorAnalyticsApiTest exercises InsightEngine's rules directly against a
 * crafted payload rather than through the HTTP layer. See
 * InsightAssistantApiTest for the HTTP-level (auth, validation, routing)
 * coverage.
 */
class InsightQueryEngineTest extends TestCase
{
    public function test_summary_question_is_answered_from_measured_figures(): void
    {
        $payload = $this->basePayload();
        $payload['summary']['grossRevenue'] = ['value' => 900.0];
        $payload['summary']['avgOrderValue'] = ['value' => 30.0];

        $result = (new InsightQueryEngine)->ask($payload, 'How much revenue did I make this week?');

        $this->assertTrue($result['matched']);
        $this->assertSame('summary', $result['topic']);
        $this->assertStringContainsString('30', $result['answer']);
        $this->assertSame(30, $result['data']['orders']);
    }

    public function test_busiest_and_quietest_question_is_answered(): void
    {
        $payload = $this->basePayload();
        $payload['peak'] = [
            'busiest' => ['day' => 'Fri', 'hour' => 19, 'orders' => 20],
            'quietest' => ['day' => 'Tue', 'hour' => 15, 'orders' => 2],
            'days' => [],
        ];

        $result = (new InsightQueryEngine)->ask($payload, "What's my busiest hour?");

        $this->assertTrue($result['matched']);
        $this->assertSame('busiest-quietest', $result['topic']);
        $this->assertSame('Fri', $result['data']['busiest']['day']);
        $this->assertSame('Tue', $result['data']['quietest']['day']);
    }

    public function test_unmatched_question_returns_suggestions_instead_of_a_guess(): void
    {
        $payload = $this->basePayload();

        $result = (new InsightQueryEngine)->ask($payload, 'asdkj qwleiu nonsense not a real question');

        $this->assertFalse($result['matched']);
        $this->assertNull($result['topic']);
        $this->assertNotEmpty($result['suggestedQuestions']);
    }

    public function test_topic_matches_but_missing_data_returns_a_specific_not_yet_message(): void
    {
        $payload = $this->basePayload();
        // No 'loyalty' key at all — the loyalty program has never been used.

        $result = (new InsightQueryEngine)->ask($payload, 'Is my loyalty program working?');

        $this->assertFalse($result['matched']);
        $this->assertSame('loyalty', $result['topic']);
        $this->assertSame(__('insight_assistant.not_enough_data'), $result['answer']);
    }

    public function test_follow_up_on_an_active_insight_answers_from_its_own_text(): void
    {
        $payload = $this->basePayload();
        $payload['peak'] = [
            'busiest' => ['day' => 'Fri', 'hour' => 19, 'orders' => 20],
            'quietest' => ['day' => 'Tue', 'hour' => 15, 'orders' => 1],
            'days' => [],
        ];

        $insight = collect((new InsightEngine)->derive($payload))->firstWhere('id', 'quiet-hour');
        $this->assertNotNull($insight, 'precondition: quiet-hour insight must fire for this payload');

        $why = (new InsightQueryEngine)->ask($payload, 'Why does this matter?', 'quiet-hour');
        $this->assertTrue($why['matched']);
        $this->assertSame('insight-followup', $why['topic']);
        $this->assertSame($insight['whyMatters'], $why['answer']);

        $action = (new InsightQueryEngine)->ask($payload, 'What should I do about it?', 'quiet-hour');
        $this->assertSame($insight['suggestedAction'], $action['answer']);

        $default = (new InsightQueryEngine)->ask($payload, 'Tell me more', 'quiet-hour');
        $this->assertSame($insight['whatHappening'], $default['answer']);
    }

    public function test_follow_up_with_an_id_that_is_not_currently_active_falls_back_gracefully(): void
    {
        $payload = $this->basePayload();

        $result = (new InsightQueryEngine)->ask($payload, 'Why?', 'not-a-real-insight-id');

        $this->assertFalse($result['matched']);
        $this->assertSame(__('insight_assistant.insight_not_active'), $result['answer']);
        $this->assertNotEmpty($result['suggestedQuestions']);
    }

    public function test_suggested_questions_only_surface_topics_with_enough_data(): void
    {
        $payload = $this->basePayload();

        $questions = (new InsightQueryEngine)->suggestedQuestions($payload);

        // Only summary() clears its gate on the minimal payload.
        $this->assertCount(1, $questions);
    }

    /** Minimal payload shape that clears InsightEngine's own sample gate, matching VendorAnalyticsApiTest::basePayload(). */
    private function basePayload(): array
    {
        return [
            'hasData' => true,
            'currency' => 'EUR',
            'period' => 'weekly',
            'summary' => ['orders' => ['value' => 30]],
        ];
    }
}
