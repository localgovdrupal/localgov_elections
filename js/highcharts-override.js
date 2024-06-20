/**
 * @file Override charts libraries.
 */

(function (Drupal, once) {
  Drupal.localgov_elections = Drupal.localgov_elections || {};
  Drupal.localgov_elections.categoryFormatter = function () {
    let val = this.value.trim();
    let color = null;
    for (const [key, value] of Object.entries(drupalSettings.localgov_elections.parties)) {
      if (val.includes(value.full_name)) {
        color = value.colour;
        break;
      }
    }
    return "<div class='label-wrapper'>" + `<span class='dot' style='background-color: ${color}'></span>` + "<div>" + this.value + "</div>" + "</div>";
  };

  Drupal.localgov_elections.charts_data = {};

  Drupal.localgov_elections.getChartInfo = function (data) {
    let d = [];
    let n = data.xAxis[0].categories.length;
    for (let i = 0; i < n; i++) {
      d.push({
        "i": i,
        "label": data.xAxis[0].categories[i].trim(),
        "val": data.series[0].data[i]
      })
    }
    Drupal.localgov_elections.charts_data = d;
  };

  Drupal.localgov_elections.setChartColours = function (chart_data, settings) {
    Drupal.localgov_elections.getChartInfo(chart_data)
    chart_data.series[0].data.forEach((entry, i) => {
      // get data from earlier
      let _data = Drupal.localgov_elections.charts_data[i];
      for (const [key, value] of Object.entries(settings.localgov_elections.parties)) {
        if (_data.label.includes(value.full_name)) {
          chart_data.series[0].data[i] = {y: entry, color: value.colour};
          break;
        }
      }
    });
  };

  Drupal.behaviors.charts_override = {
    attach: function (context, settings) {
      const highCharts = once('allHighCharts', '.charts-highchart', context);
      if (highCharts) {
        highCharts.forEach(chart => {
          chart.addEventListener('drupalChartsConfigsInitialization', function (e) {
            let data = e.detail;
            const id = data.drupalChartDivId;

            Drupal.localgov_elections.setChartColours(data, settings)

            if (id === 'chart-election-results-via-parties-block-1') {
              data.xAxis[0].labels.useHTML = true;
              data.xAxis[0].labels.formatter = Drupal.localgov_elections.categoryFormatter;
              data.xAxis[0].labels.align = 'left';
              data.xAxis[0].labels.reserveSpace = true;
              Drupal.Charts.Contents.update(id, data);
            }

            if (id === 'chart-district-results-default') {
              data.yAxis[0].labels = {
                enabled: false
              }
              data.xAxis[0].gridLineWidth = 1;
              data.yAxis[0].gridLineWidth = 0;
              data.tooltip.enabled = false;
              data.xAxis[0].labels.align = 'left';
              data.xAxis[0].labels.reserveSpace = true;
              data.xAxis[0].labels.useHTML = true;
              data.xAxis[0].labels.formatter = Drupal.localgov_elections.categoryFormatter;
              Drupal.Charts.Contents.update(id, data);
            }
          });
        });
      }
    }
  };
})(Drupal, once);
