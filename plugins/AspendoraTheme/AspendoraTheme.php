<?php

namespace Piwik\Plugins\AspendoraTheme;

use Piwik\Mail\EmailStyles;
use Piwik\Plugin;

/**
 * Aspendora brand theme.
 *
 * Colours are the canonical brand tokens from ~/code/aspendora-branding/tokens/tokens.json
 * (mirrored in docs/branding.md): brand blue #2563eb on a slate scale, with the navy family for
 * dark chrome and dark mode. Every value is a [light, dark] pair because Matomo resolves the
 * theme once per mode; the top bar is deliberately navy in *both* modes, which is what the
 * all-white reverse lockup in images/logo-header.png is for.
 *
 * The logo crimson is identity only and never appears as a UI colour — brand rule 1.
 */
class AspendoraTheme extends Plugin
{
    public const BRAND_NAME = 'Aspendora Analytics';

    /** color.brand.blue / blue-hover / blue-light */
    private const BLUE = '#2563eb';
    private const BLUE_HOVER = '#1d4ed8';
    private const BLUE_LIGHT = '#60a5fa';

    /** color.brand.navy family + color.neutral scale */
    private const INK = '#0f1729';
    private const NAVY_HERO = '#0f172a'; // dark-section background as shipped
    private const NAVY = '#0c1a36';      // deepest brand navy — footers; our dark-mode page base
    private const NAVY_DEEP = '#142850'; // gradient partner to navy; our dark-mode card surface
    private const SLATE_700 = '#334155';
    private const SLATE_600 = '#475569';
    private const SLATE_500 = '#64748b';
    private const SLATE_400 = '#94a3b8';
    private const SLATE_300 = '#cbd5e1';
    private const SLATE_200 = '#e2e8f0';
    private const SLATE_100 = '#f1f5f9';
    private const SLATE_50 = '#f8fafc';
    private const WHITE = '#ffffff';

    public function registerEvents()
    {
        return [
            'Theme.configureThemeVariables' => 'configureThemeVariables',
            'Email.configureEmailStyle' => 'configureEmailStyle',
        ];
    }

    public function configureThemeVariables(Plugin\ThemeStyles $vars): void
    {
        $vars->fontFamilyBase = "'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

        // Brand
        $vars->colorBrand = [self::BLUE, self::BLUE_LIGHT];
        $vars->colorBrandContrast = [self::WHITE, self::NAVY];
        $vars->colorNewBrand = self::BLUE;
        $vars->colorLink = [self::BLUE, self::BLUE_LIGHT];
        $vars->colorBaseSeries = self::BLUE;
        $vars->colorFocusRing = self::BLUE;
        $vars->colorFocusRingAlternative = self::BLUE_HOVER;

        // Text
        $vars->colorTextHighContrast = ['#020617', self::SLATE_50];
        $vars->colorText = [self::INK, self::SLATE_200];
        $vars->colorTextContrast = [self::SLATE_700, self::SLATE_300];
        $vars->colorTextLight = [self::SLATE_600, self::SLATE_400];
        $vars->colorTextLighter = [self::SLATE_500, self::SLATE_400];
        $vars->colorTextOnDisabled = [self::SLATE_500, self::SLATE_400];
        $vars->colorTextPlaceholder = [self::SLATE_400, self::SLATE_600];
        $vars->colorTextDisabled = [self::SLATE_400, self::SLATE_600];
        $vars->colorTextInvert = [self::SLATE_200, self::SLATE_600];
        $vars->colorTextInvertContrast = [self::WHITE, self::NAVY];
        $vars->colorTextInvertLight = [self::SLATE_300, self::SLATE_500];
        $vars->colorHeadlineAlternative = [self::SLATE_700, self::SLATE_300];

        // Surfaces — dark mode is the brand navy family: navy as the page, navy-deep as the
        // raised card, so widgets read as lifted rather than outlined.
        $vars->colorBackgroundBase = [self::SLATE_50, self::NAVY];
        $vars->colorBackgroundTinyContrast = [self::SLATE_100, self::NAVY_HERO];
        $vars->colorBackgroundLowContrast = [self::SLATE_200, self::NAVY_DEEP];
        $vars->colorBackgroundContrast = [self::WHITE, self::NAVY_DEEP];
        $vars->colorBackgroundHighContrast = [self::NAVY_HERO, self::SLATE_700];
        $vars->colorBackgroundDisabled = [self::SLATE_200, self::NAVY_HERO];

        // Borders and depth — the site leans on hairlines rather than drop shadows
        $vars->colorBorder = [self::SLATE_200, self::NAVY_HERO];
        $vars->colorBorderAlternative = [self::SLATE_200, self::NAVY_HERO];
        $vars->colorBorderLight = [self::SLATE_300, self::SLATE_700];
        $vars->colorBoxShadow = ['rgba(15, 23, 41, 0.08)', 'rgba(0, 0, 0, 0.45)'];
        $vars->shadowOverlay = [
            '0 1px 3px rgba(15, 23, 41, 0.06), 0 10px 30px rgba(15, 23, 41, 0.10)',
            '0 1px 3px rgba(0, 0, 0, 0.5), 0 10px 30px rgba(0, 0, 0, 0.5)',
        ];

        // Top bar — navy in both modes, which is what images/logo-header.png is knocked out for
        $vars->colorHeaderBackground = [self::NAVY_HERO, self::NAVY];
        $vars->colorHeaderText = [self::SLATE_200, self::SLATE_200];

        // Left menu
        $vars->colorMenuContrastBackground = [self::WHITE, self::NAVY_DEEP];
        $vars->colorMenuContrastBackgroundHover = [self::SLATE_100, self::NAVY_HERO];
        $vars->colorMenuContrastText = [self::SLATE_600, self::SLATE_300];
        $vars->colorMenuContrastTextActive = [self::BLUE, self::BLUE_LIGHT];
        $vars->colorMenuContrastTextSelected = [self::INK, self::SLATE_50];

        // Widgets
        $vars->colorWidgetBackground = [self::WHITE, self::NAVY_DEEP];
        $vars->colorWidgetBorder = [self::SLATE_200, self::NAVY_HERO];
        $vars->colorWidgetTitleBackground = [self::WHITE, self::NAVY_DEEP];
        $vars->colorWidgetTitleText = [self::INK, self::SLATE_200];
        $vars->colorWidgetExportedBackgroundBase = [self::WHITE, self::NAVY_DEEP];
    }

    /**
     * Scheduled-report emails render their own header/footer; this is the only place the
     * product name is exposed to recipients outside a template.
     *
     * EmailStyles seeds itself from the ReportRenderer constants, which this fork has set to
     * the brand's *document* palette so PDFs come out right. Burgundy headings are deliberately
     * a print-only convention (see ~/code/aspendora-branding/docs/color.md), so the title colour
     * is put back to web ink for the HTML email. Table styling is shared on purpose — it should
     * be the same report whichever way it arrives.
     */
    public function configureEmailStyle(EmailStyles $styles): void
    {
        $styles->brandNameLong = self::BRAND_NAME;
        $styles->reportTitleTextColor = self::INK;
    }
}
