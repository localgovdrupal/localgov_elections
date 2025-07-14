/**
 * @file Override charts libraries.
 */

(function lgdElectionsScript(Drupal, once) {
  Drupal.localgov_elections = Drupal.localgov_elections || {};

  Drupal.localgov_elections.setChartColours = function setChartColours(
    chartData,
    settings,
  ) {
    const data = [];

    // Strip out rows with no content in label.
    chartData.data.labels.forEach(function stripEmptyLabels(entry, i) {
      if (entry === '') {
        chartData.data.datasets[0].data.splice(i, 1);
        chartData.data.labels.splice(i, 1);
      }
    });

    chartData.data.labels.forEach(function setRowColour(entry) {
      // Find the background colour, so we can apply it to the row.
      const found = Object.entries(settings.localgov_elections.parties).find(
        ([, value]) => entry.includes(value.full_name),
      );
      if (found) {
        data.push(found[1].colour);
      } else {
        data.push('#ffffff');
      }
    });
    chartData.data.datasets[0].backgroundColor = data;
    chartData.options.scales.x.ticks.precision = 0;
  };

  Drupal.behaviors.charts_override = {
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
