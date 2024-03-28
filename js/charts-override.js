(function ($, Drupal) {
  Drupal.localgov_elections_reporting = Drupal.localgov_elections_reporting || {};
  Drupal.localgov_elections_reporting.categoryFormatter = function () {
    let val = this.value.trim();
    let color = null;
    for (const [key, value] of Object.entries(drupalSettings.localgov_elections_reporting.parties)) {
      if (val.includes(value.full_name)) {
        color = value.colour;
        break;
      }
    }
    return "<div class='label-wrapper'>" + `<span class='dot' style='background-color: ${color}'></span>` + "<div>" + this.value + "</div>" + "</div>";
  };

  Drupal.localgov_elections_reporting.charts_data = {};

  Drupal.localgov_elections_reporting.getChartInfo = function (data) {
    let d = [];
    let n = data.xAxis[0].categories.length;
    for (let i = 0; i < n; i++) {
      d.push({
        "i": i,
        "label": data.xAxis[0].categories[i].trim(),
        "val": data.series[0].data[i]
      })
    }
    Drupal.localgov_elections_reporting.charts_data = d;
  };

  Drupal.localgov_elections_reporting.setChartColours = function (chart_data, settings) {
    Drupal.localgov_elections_reporting.getChartInfo(chart_data)
    chart_data.series[0].data.forEach((entry, i) => {
      // get data from earlier
      let _data = Drupal.localgov_elections_reporting.charts_data[i];
      for (const [key, value] of Object.entries(settings.localgov_elections_reporting.parties)) {
        if (_data.label.includes(value.full_name)) {
          chart_data.series[0].data[i] = {y: entry, color: value.colour};
          break;
        }
      }
    });
  };

  Drupal.behaviors.charts_override = {
    attach: function (context, settings) {
      document.querySelectorAll('.charts-highchart').forEach(function (el) {
        el.addEventListener('drupalChartsConfigsInitialization', function (e) {
          let data = e.detail;
          const id = data.drupalChartDivId;

          Drupal.localgov_elections_reporting.setChartColours(data, settings)

          if (id === 'chart-election-results-via-parties-block-1') {
            data.xAxis[0].labels.useHTML = true;
            data.xAxis[0].labels.formatter = Drupal.localgov_elections_reporting.categoryFormatter;
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
            data.xAxis[0].labels.formatter = Drupal.localgov_elections_reporting.categoryFormatter;
            Drupal.Charts.Contents.update(id, data);
          }
        });
      });

    }
  };
})(jQuery, Drupal);

