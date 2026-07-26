<?php
/**
 * Aspendora Experiments — A/B test reporting.
 *
 * Variants are assigned client-side (bundle.js: window.__asp.exp config,
 * persistent per device, html class asp-exp-<name>-<variant>) and tracked as
 * events: category "Experiment", action = experiment name, name = variant.
 * Conversions = Matomo goal conversions + the event categories configured for
 * AspendoraAttribution (ASPENDORA_ATTRIB_EVENTS, default Newsletter).
 */

namespace Piwik\Plugins\AspendoraExperiments;

class AspendoraExperiments extends \Piwik\Plugin
{
}
