/**
 * @file Election party colours.
 */

(function (Drupal, once) {
  Drupal.behaviors.lgdElectionPartyColours = {
    attach: function (context, settings) {
      const parties = once(
        'allParties',
        'div.party, span.party',
        context,
      );
      console.log(parties);
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
                }
              );
            }
          });
        });
      }
    }
  };
})(Drupal, once);
