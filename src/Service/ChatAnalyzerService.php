<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ChatAnalysis;
use App\Enum\ChatCategory;
use App\Enum\ChatSentiment;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

// Handles communication with the LLM and returns a validated ChatAnalysis.
final class ChatAnalyzerService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openRouterApiKey,
        private readonly string $openRouterBaseUrl,
    ) {
    }

    /**
     * @param array $messages Full conversation history — not just the last message.
     *                        This is required for the LLM to understand context references.
     */
    public function analyze(array $messages): ChatAnalysis
    {
        $payload = [
            'model' => 'openai/gpt-4o-mini',
            // Forces the model to return strict JSON with no surrounding text or markdown.
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->buildSystemPrompt()],
                ...$messages,
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $this->openRouterBaseUrl . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openRouterApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => $payload,
                'timeout' => 20,
            ]);

            $data = $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new ChatAnalysisException(
                'Could not reach the AI service: ' . $e->getMessage(),
                previous: $e
            );
        }

        $rawContent = $data['choices'][0]['message']['content'] ?? null;

        if ($rawContent === null) {
            throw new ChatAnalysisException('Unexpected response format from the LLM.');
        }

        $decoded = json_decode($rawContent, true);

        if (!is_array($decoded)) {
            throw new ChatAnalysisException('The LLM returned invalid JSON.');
        }

        try {
            return ChatAnalysis::fromArray($decoded);
        } catch (\ValueError $e) {
            throw new ChatAnalysisException(
                'The LLM returned a value outside the allowed catalog: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    // Enum values are injected dynamically so the prompt stays in sync
    // with the enum definition automatically — no manual updates needed.
    private function buildSystemPrompt(): string
    {
        $categories = ChatCategory::values();
        $sentiments = ChatSentiment::values();

        return <<<PROMPT
            You are a customer support triage assistant. Analyze the full conversation
            (not just the last message) and return ONLY a JSON object with this exact structure:

            {
              "category": "{$categories}",
              "sentiment": "{$sentiments}",
              "summary": "one sentence summary of the issue, max 15 words",
              "suggested_reply": "a draft reply the human agent could send to the customer",
              "urgency": 1 to 5, where 5 is a critical case requiring immediate attention
                (frustrated customer, payment issue, imminent churn, explicit urgency)
                and 1 is a purely informational query with no time pressure
            }

            Do not include any text outside the JSON. Do not use markdown or code blocks.
            PROMPT;
    }
}