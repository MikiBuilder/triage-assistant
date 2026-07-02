<?php

declare(strict_types=1);

namespace App\Service;

// Specific exception for LLM analysis failures.
// Keeping it separate from generic exceptions allows the controller
// to catch it precisely and isolate failures per chat.
final class ChatAnalysisException extends \RuntimeException
{
}