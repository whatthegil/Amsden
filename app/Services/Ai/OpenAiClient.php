<?php

namespace App\Services\Ai;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around the OpenAI Chat Completions API (raw HTTP via Guzzle,
 * same approach as the project's other HTTP-based service wrappers — no
 * extra SDK dependency needed).
 */
class OpenAiClient
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public static function isConfigured(): bool
    {
        return filled(config('services.openai.api_key'));
    }

    /**
     * Send a single non-streaming Chat Completions request and return the
     * parsed JSON body. Returns null (never throws) on any failure — every
     * caller in this app treats AI enhancement as optional, so a network
     * blip or bad key degrades to the existing non-AI behavior instead of
     * breaking the page.
     */
    public static function createChatCompletion(array $params): ?array
    {
        if (!self::isConfigured()) {
            return null;
        }

        $client = new Client(['timeout' => 30, 'connect_timeout' => 10]);

        try {
            $response = $client->post(self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                    'Content-Type'  => 'application/json',
                ],
                'json' => array_merge(['model' => config('services.openai.model')], $params),
            ]);

            $body = json_decode((string) $response->getBody(), true);
            if (!is_array($body)) {
                throw new RuntimeException('Non-JSON response from OpenAI API');
            }

            return $body;
        } catch (GuzzleException $e) {
            Log::warning('OpenAI API request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convenience helper for the common case: a request that asks for a
     * single JSON object matching $schema via Structured Outputs, returned
     * already-decoded. Returns null on any failure (refusal, malformed
     * response, network error, missing key) — callers fall back gracefully.
     *
     * Note: OpenAI's strict Structured Outputs mode requires every object
     * in $schema to set "additionalProperties": false and list every key
     * (including optional ones) in "required" — see platform docs.
     */
    public static function createJsonMessage(string $system, string $userContent, array $schema, int $maxTokens = 2048): ?array
    {
        $body = self::createChatCompletion([
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userContent],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name'   => 'response',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        if ($body === null) {
            return null;
        }

        $message = $body['choices'][0]['message'] ?? null;
        if ($message === null) {
            return null;
        }

        if (!empty($message['refusal'])) {
            Log::warning('OpenAI API refused the request', ['refusal' => $message['refusal']]);
            return null;
        }

        $content = $message['content'] ?? null;
        if (!is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
