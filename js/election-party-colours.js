(function ($, Drupal) {

  Drupal.behaviors.party_colours = {
    attach: function (context, settings) {
      let box = $('.box');
      if (box.length >= 1) {
        let classList = box[0].className.split(/\s+/);
        classList.forEach(i => {
          if (i !== 'box') {
            for (const [key, value] of Object.entries(settings.localgov_elections_reporting.parties)) {
              if (i.includes(value.abbr) || i.includes(value.full_name)) {
                box[0].style.backgroundColor = value.colour;
                box[0].style.color = value["text-colour"];
                break;
              }
            }
          }
        })
      }


      let parties = $('div.party, span.winning-party');
      parties.each((i, el) => {
        let classList = el.className.split(/\s+/);
        classList.forEach(i => {
          if (i !== 'party') {
            for (const [key, value] of Object.entries(settings.localgov_elections_reporting.parties)) {
              if (i.includes(value.abbr) || i.includes(value.full_name)) {
                el.style.backgroundColor = value.colour;
                el.style.color = value["text-colour"];
              }
            }
          }
        });
      });
    }
  }
})(jQuery, Drupal);
