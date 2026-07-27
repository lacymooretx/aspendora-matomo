<?php

namespace Piwik\Plugins\AspendoraInsights;

use Exception;

/**
 * Minimal Claude API client (raw curl by repo convention — see plugin header).
 * Model claude-opus-5; thinking is on by default on this model, so no
 * thinking parameter is sent. Ships with the server-side refusal fallback
 * enabled (fallbacks: "default") so a classifier false-positive still
 * returns a usable digest.
 */
class ClaudeClient
{
    private const MODEL = 'claude-opus-5';

    public function isConfigured(): bool
    {
        return (bool) getenv('ASPENDORA_ANTHROPIC_KEY');
    }

    public function complete(string $system, string $userContent, int $maxTokens = 3000): string
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . getenv('ASPENDORA_ANTHROPIC_KEY'),
                'anthropic-version: 2023-06-01',
                'anthropic-beta: server-side-fallback-2026-07-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode([
                'model'      => self::MODEL,
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'fallbacks'  => 'default',
                'messages'   => [['role' => 'user', 'content' => $userContent]],
            ]),
            CURLOPT_TIMEOUT        => 300,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false) {
            throw new Exception('Claude API request failed: ' . $err);
        }
        $resp = json_decode($raw, true) ?: [];
        if ($code >= 400) {
            throw new Exception('Claude API HTTP ' . $code . ': ' . substr($raw, 0, 300));
        }
        if (($resp['stop_reason'] ?? '') === 'refusal') {
            throw new Exception('Claude declined the request (stop_reason: refusal)');
        }
        $text = '';
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if ($text === '') {
            throw new Exception('Claude returned no text (stop_reason: ' . ($resp['stop_reason'] ?? '?') . ')');
        }
        return $text;
    }
}
