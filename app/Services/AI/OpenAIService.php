<?php

namespace App\Services\AI;

use App\Exceptions\AIServiceException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected string $apiKey;
    protected string $chatModel;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = (string) config('services.openai.key');
        $this->chatModel = (string) config('services.openai.chat_model');
        $this->timeout = (int) config('services.openai.timeout', 60);
    }

    /**
     * Send a chat completion request and return the raw message content.
     */
    public function chat(array $messages, bool $jsonMode = false): string
    {
        $this->ensureConfigured();

        $payload = [
            'model' => $this->chatModel,
            'messages' => $messages,
            'temperature' => 0.8,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken($this->apiKey)
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        $this->ensureSuccessful($response, 'generate post content');

        $content = $response->json('choices.0.message.content');

        if (!$content) {
            throw new AIServiceException('AI returned an empty response.', 502);
        }

        return $content;
    }

    protected function ensureConfigured(): void
    {
        if (empty($this->apiKey)) {
            throw new AIServiceException('AI service is not configured.', 500);
        }
    }

    protected function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        Log::error("OpenAI API failed to {$action}", [
            'status' => $status,
            'body' => $response->json() ?? $response->body(),
        ]);

        if ($status === 429) {
            throw new AIServiceException('AI service is busy right now. Please try again shortly.', 429);
        }

        if (in_array($status, [401, 403], true)) {
            throw new AIServiceException('AI service configuration error.', 500);
        }

        if ($status === 400) {
            throw new AIServiceException($response->json('error.message') ?: 'Invalid AI request.', 422);
        }

        throw new AIServiceException('AI service is currently unavailable. Please try again later.', 502);
    }
}
