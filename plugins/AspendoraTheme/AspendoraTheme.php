<?php

namespace Piwik\Plugins\AspendoraTheme;

use Piwik\Mail\EmailStyles;
use Piwik\Plugin;

/**
 * Aspendora brand theme.
 *
 * Colours come from the live aspendora.com build (see ~/code/aspendora-existingwebsite):
 * brand blue #2563eb on a slate scale, with #0f172a navy for dark chrome. Every value is a
 * [light, dark] pair because Matomo resolves the theme once per mode; a bare string is used
 * only where the colour is deliberately mode-independent (the navy top bar, which stays dark
 * in light mode so the knockout logo works in both).
 */
class AspendoraTheme extends Plugin
{
    public const BRAND_NAME = 'Aspendora Analytics';

    /** aspendora.com brand blue / hover / light-on-dark variant */
    private const BLUE = '#2563eb';
    private const BLUE_HOVER = '#1d4ed8';
    private const BLUE_LIGHT = '#60a5fa';

    /** slate scale, as used across the site's sections */
    private const INK = '#0f1729';
    private const NAVY = '#0f172a';
    private const NAVY_DEEP = '#0b1120';
    private const SURFACE_DARK = '#111a2e';
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

        // Surfaces
        $vars->colorBackgroundBase = [self::SLATE_50, self::NAVY_DEEP];
        $vars->colorBackgroundTinyContrast = [self::SLATE_100, '#16213a'];
        $vars->colorBackgroundLowContrast = [self::SLATE_200, '#1e293b'];
        $vars->colorBackgroundContrast = [self::WHITE, self::SURFACE_DARK];
        $vars->colorBackgroundHighContrast = [self::NAVY, self::SLATE_700];
        $vars->colorBackgroundDisabled = [self::SLATE_200, '#1e293b'];

        // Borders and depth — the site leans on hairlines rather than drop shadows
        $vars->colorBorder = [self::SLATE_200, '#1e293b'];
        $vars->colorBorderAlternative = [self::SLATE_200, '#1e293b'];
        $vars->colorBorderLight = [self::SLATE_300, self::SLATE_700];
        $vars->colorBoxShadow = ['rgba(15, 23, 41, 0.08)', 'rgba(0, 0, 0, 0.45)'];
        $vars->shadowOverlay = [
            '0 1px 3px rgba(15, 23, 41, 0.06), 0 10px 30px rgba(15, 23, 41, 0.10)',
            '0 1px 3px rgba(0, 0, 0, 0.5), 0 10px 30px rgba(0, 0, 0, 0.5)',
        ];

        // Top bar — navy in both modes, which is what images/logo-header.png is knocked out for
        $vars->colorHeaderBackground = [self::NAVY, self::NAVY_DEEP];
        $vars->colorHeaderText = [self::SLATE_200, self::SLATE_200];

        // Left menu
        $vars->colorMenuContrastBackground = [self::WHITE, self::SURFACE_DARK];
        $vars->colorMenuContrastBackgroundHover = [self::SLATE_100, '#16213a'];
        $vars->colorMenuContrastText = [self::SLATE_600, self::SLATE_300];
        $vars->colorMenuContrastTextActive = [self::BLUE, self::BLUE_LIGHT];
        $vars->colorMenuContrastTextSelected = [self::INK, self::SLATE_50];

        // Widgets
        $vars->colorWidgetBackground = [self::WHITE, self::SURFACE_DARK];
        $vars->colorWidgetBorder = [self::SLATE_200, '#1e293b'];
        $vars->colorWidgetTitleBackground = [self::WHITE, self::SURFACE_DARK];
        $vars->colorWidgetTitleText = [self::INK, self::SLATE_200];
        $vars->colorWidgetExportedBackgroundBase = [self::WHITE, self::SURFACE_DARK];
    }

    /**
     * Scheduled-report emails render their own header/footer; this is the only place the
     * product name is exposed to recipients outside a template.
     */
    public function configureEmailStyle(EmailStyles $styles): void
    {
        $styles->brandNameLong = self::BRAND_NAME;
    }
}
