<?php

namespace App\Services\Ai;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around the Claude Messages API (raw HTTP via Guzzle).
 *
 * The official anthropic-ai/sdk requires PHP ^8.1; this project targets
 * ^8.0 (composer.json, and the installed CLI is 8.0.30), so the SDK cannot
 * be installed here. Guzzle is already a project dependency, so this talks
 * to POST /v1/messages directly instead.
 */
class AnthropicClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public static function isConfigured(): bool
    {
        return filled(config('services.anthropic.api_key'));
    }

    /**
     * Send a single non-streaming Messages API request and return the
     * parsed JSON body. Returns null (never throws) on any failure — every
     * caller in this app treats AI enhancement as optional, so a network
     * blip or bad key degrades to the existing non-AI behavior instead of
     * breaking the page.
     */
    public static function createMessage(array $params): ?array
    {
        if (!self::isConfigured()) {
            return null;
        }

        $client = new Client(['timeout' => 30, 'connect_timeout' => 10]);

        try {
            $response = $client->post(self::API_URL, [
                'headers' => [
                    'x-api-key'         => config('services.anthropic.api_key'),
                    'anthropic-version' => self::API_VERSION,
                    'content-type'      => 'application/json',
                ],
                'json' => array_merge(['model' => config('services.anthropic.model')], $params),
            ]);

            $body = json_decode((string) $response->getBody(), true);
            if (!is_array($body)) {
                throw new RuntimeException('Non-JSON response from Anthropic API');
            }

            return $body;
        } catch (GuzzleException $e) {
            Log::warning('Anthropic API request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convenience helper for the common case: a request that asks for a
     * single JSON object matching $schema via structured outputs, returned
     * already-decoded. Returns null on any failure (refusal, malformed
     * response, network error, missing key) — callers fall back gracefully.
     */
    public static function createJsonMessage(string $system, string $userContent, array $schema, int $maxTokens = 2048): ?array
    {
        $body = self::createMessage([
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $userContent]],
            'output_config' => [
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
        ]);

        if ($body === null) {
            return null;
        }

        if (($body['stop_reason'] ?? null) === 'refusal') {
            Log::warning('Anthropic API refused the request', ['stop_details' => $body['stop_details'] ?? null]);
            return null;
        }

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $decoded = json_decode($block['text'], true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }
}
