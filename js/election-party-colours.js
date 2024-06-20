/**
 * @file Election party colours.
 **/

(function (Drupal, once) {

  Drupal.behaviors.party_colours = {
    attach: function (context, settings) {

      const boxes = once('allBoxes', '.box', context);
      if (boxes && boxes.length > 0) {
        const box = boxes[0];
        let classList = box.className.split(/\s+/);
        classList.forEach(i => {
          if (i !== 'box') {
            for (const [key, value] of Object.entries(settings.localgov_elections_reporting.parties)) {
              if (i.includes(value.abbr) || i.includes(value.full_name)) {
                box.style.backgroundColor = value.colour;
                box.style.color = value["text-colour"];
                break;
              }
            }
          }
        });
      }

      const parties = once('allParties', 'div.party, span.winning-party', context);
      if (parties) {
        parties.forEach(party => {
          let classList = party.className.split(/\s+/);
          classList.forEach(i => {
            if (i !== 'party') {
              for (const [key, value] of Object.entries(settings.localgov_elections_reporting.parties)) {
                if (i.includes(value.abbr) || i.includes(value.full_name)) {
                  party.style.backgroundColor = value.colour;
                  party.style.color = value["text-colour"];
                }
              }
            }
          });
        });
      }
    }
  }
})(Drupal, once);