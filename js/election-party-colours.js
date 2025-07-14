/**
 * @file Election party colours.
 * */

function lgdElectionPartyColoursScript(Drupal, once) {
  Drupal.behaviors.lgdElectionPartyColours = {
    attach(context, settings) {
      const boxes = once('allBoxes', '.box', context);
      if (boxes && boxes.length > 0) {
        const box = boxes[0];
        const classList = box.className.split(/\s+/);
        classList.forEach((i) => {
          if (i !== 'box') {
            Object.entries(settings.localgov_elections.parties).forEach(
              ([, value]) => {
                if (i.includes(value.abbr) || i.includes(value.full_name)) {
                  box.style.backgroundColor = value.colour;
                  box.style.color = value['text-colour'];
                }
              },
            );
          }
        });
      }

      const parties = once(
        'allParties',
        'div.party, span.winning-party',
        context,
      );
      if (parties) {
        parties.forEach((party) => {
          const classList = party.className.split(/\s+/);
          classList.forEach((i) => {
            if (i !== 'party') {
              Object.entries(settings.localgov_elections.parties).forEach(
                ([, value]) => {
                  if (i.includes(value.abbr) || i.includes(value.full_name)) {
                    party.style.backgroundColor = value.colour;
                    party.style.color = value['text-colour'];
                  }
                },
              );
            }
          });
        });
      }
    },
  };
}(Drupal, once);
