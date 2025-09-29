/**
 * @file Override charts libraries.
 */

(function lgdElectionsChartsOverrideScript(Drupal, once) {
  Drupal.localgov_elections = Drupal.localgov_elections || {};

  /**
   * Calculate relative luminance of a color
   */
  function getLuminance(r, g, b) {
    const [rs, gs, bs] = [r, g, b].map(c => {
      c = c / 255;
      return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
  }

  /**
   * Calculate contrast ratio between two colors
   */
  function getContrastRatio(l1, l2) {
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  /**
   * Parse hex color to RGB
   */
  function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
      r: parseInt(result[1], 16),
      g: parseInt(result[2], 16),
      b: parseInt(result[3], 16)
    } : null;
  }

  /**
   * Get best contrast color (black or white) for a given background
   */
  function getContrastColor(bgColor) {
    const rgb = hexToRgb(bgColor);
    if (!rgb) return '#000000'; // fallback

    const bgLuminance = getLuminance(rgb.r, rgb.g, rgb.b);
    const whiteLuminance = 1;
    const blackLuminance = 0;

    const contrastWithWhite = getContrastRatio(bgLuminance, whiteLuminance);
    const contrastWithBlack = getContrastRatio(bgLuminance, blackLuminance);

    return contrastWithWhite > contrastWithBlack ? '#FFFFFF' : '#000000';
  }

  Drupal.localgov_elections.setChartColours = function setChartColours(
    chartData,
    settings,
  ) {
    const backgroundColors = [];
    const foregroundColors = [];

    // Strip out rows with no content in label.
    chartData.data.labels.forEach(function stripEmptyLabels(entry, i) {
      if (entry === '') {
        chartData.data.datasets[0].data.splice(i, 1);
        chartData.data.labels.splice(i, 1);
      }
    });

    chartData.data.labels.forEach((entry, index) => {
      // Find the background colour, so we can apply it to the row.
      const found = Object.entries(settings.localgov_elections.parties).find(
        ([, value]) => entry.includes(value.full_name),
      );

      let bgColor;
      if (found) {
        bgColor = found[1].colour;
      } else {
        bgColor = '#ffffff';
      }

      backgroundColors.push(bgColor);

      // Use black for zero values (no bar = white background)
      const dataValue = chartData.data.datasets[0].data[index];
      if (dataValue === 0 || dataValue === null || dataValue === undefined) {
        foregroundColors.push('#000000');
      } else {
        foregroundColors.push(getContrastColor(bgColor));
      }
    });

    chartData.data.datasets[0].backgroundColor = backgroundColors;

    // Configure datalabels plugin to show values with contrasting colors
    chartData.options.plugins.datalabels = {
      display: true,
      color: foregroundColors,
      anchor: 'center',
      align: 'center',
      font: {
        weight: 'bold',
        size: 14
      }
    };

    // Style the y-axis labels (party names)
    chartData.options.scales.y.ticks.font = {
      size: 14,
    };

    chartData.options.scales.x.ticks.precision = 0;
  };

  Drupal.behaviors.lgdElectionsChartsOverride = {
    attach(context, settings) {
      once('allChartJS', '.charts-chartjs', context).forEach((chart) => {
        chart.addEventListener(
          'drupalChartsConfigsInitialization',
          function handleChartsConfigsInitialization(e) {
            const data = e.detail;
            const id = data.drupalChartDivId;
            Drupal.localgov_elections.setChartColours(data, settings);
            if (
              id === 'chart-election-results-via-parties-block-1' ||
              id === 'chart-localgov-election-results-via-parties-block-1'
            ) {
              data.options.scales.y.grid = { display: false };
              data.options.scales.y.ticks.autoSkip = false;
            }
          },
        );
      });
    },
  };
})(Drupal, once);
