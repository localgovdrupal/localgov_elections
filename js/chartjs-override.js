/**
 * @file Override charts libraries.
 */

(function (Drupal, once) {
  Drupal.localgov_elections = Drupal.localgov_elections || {};

  Drupal.localgov_elections.setChartColours = function (chart_data, settings) {
    let data = [];

    // Strip out rows with no content in label.
    chart_data.data.labels.forEach((entry, i) => {
      if (entry === ""){
        chart_data.data.datasets[0].data.splice(i,1);
        chart_data.data.labels.splice(i,1);
      }
    });

    chart_data.data.labels.forEach((entry, i) => {
      let colour = null;
      // Find the background colour, so we can apply it to the row.
      for (const [key, value] of Object.entries(settings.localgov_elections.parties)) {
        if (entry.includes(value.full_name)) {
          colour = value.colour;
          break;
        }
      }
      if (colour){
        data.push(colour)
      } else {
        data.push('#ffffff');
      }
      colour = null;
    });
    chart_data.data.datasets[0].backgroundColor = data;
    chart_data.options.scales.x.ticks.precision = 0;
  };

  Drupal.behaviors.charts_override = {
    attach: function (context, settings) {
      once('allChartJS', '.charts-chartjs', context).forEach(chart => {
          chart.addEventListener('drupalChartsConfigsInitialization', function (e) {
            let data = e.detail;
            const id = data.drupalChartDivId;
            Drupal.localgov_elections.setChartColours(data, settings);
            if (id === 'chart-election-results-via-parties-block-1') {
              data.options.scales.y.grid = { display: false};
              data.options.scales.y.ticks.autoSkip = false;
            }
          });
        });
      }
  };
})(Drupal, once);