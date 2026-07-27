<?php
/**
 * Aspendora Insights — weekly AI-written analytics digest.
 *
 * Configuration comes from environment variables (never in git):
 *   ASPENDORA_ANTHROPIC_KEY — Claude API key (console.anthropic.com); required
 *                             for the narrative — without it the digest is skipped
 *   ASPENDORA_INSIGHTS_EMAIL — recipient; falls back to ASPENDORA_ALERT_EMAIL
 *
 * Note: raw curl instead of the official Anthropic PHP SDK on purpose — every
 * external integration in this fork (GSC, GHL, IPinfo) is a minimal curl
 * client so composer stays untouched and upstream Matomo merges stay clean.
 */

namespace Piwik\Plugins\AspendoraInsights;

class AspendoraInsights extends \Piwik\Plugin
{
    public static function recipient(): ?string
    {
        $email = getenv('ASPENDORA_INSIGHTS_EMAIL') ?: getenv('ASPENDORA_ALERT_EMAIL') ?: '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
