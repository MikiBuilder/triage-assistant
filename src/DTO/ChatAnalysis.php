<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\ChatCategory;
use App\Enum\ChatSentiment;

// Immutable data structure representing the validated result of a single chat analysis.
// Using readonly ensures the data cannot be modified after construction.
final readonly class ChatAnalysis
{
    public function __construct(
        public ChatCategory $category,
        public ChatSentiment $sentiment,
        public string $summary,
        public string $suggestedReply,
        public int $urgency,
    ) {
    }

    // Builds the DTO from the decoded JSON returned by the LLM.
    // If the LLM returns a value outside the enum catalog, Enum::from()
    // throws a ValueError which is caught upstream in the service.
    public static function fromArray(array $data): self
    {
        return new self(
            category: ChatCategory::from($data['category']),
            sentiment: ChatSentiment::from($data['sentiment']),
            summary: $data['summary'],
            suggestedReply: $data['suggested_reply'],
            urgency: (int) $data['urgency'],
        );
    }

    public function toArray(): array
    {
        return [
            'category'       => $this->category->value,
            'sentiment'      => $this->sentiment->value,
            'summary'        => $this->summary,
            'suggested_reply' => $this->suggestedReply,
            'urgency'        => $this->urgency,
        ];
    }
}