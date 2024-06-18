/**
 * @file JS functions for the election menu block.
 */
(function electionMenuScript(Drupal) {
  Drupal.behaviors.electionMenu = {
    attach: function (context) {
      const electionMenuBlocks = once('allElectionMenuBlocks', '.election-menu-block', context);

      // This reset function is used to reset the block to its default state.
      function handleResetMenu(menu, block) {
        menu.removeAttribute('aria-hidden');
        const menuButtonContainer = block.querySelector('.election-menu-block__button-container');
        if (menuButtonContainer) {
          menuButtonContainer.remove();
        }
      }

      // This function is used to set up the menu toggle for mobile.
      function handleSetUpMenu() {
        if (electionMenuBlocks) {
          electionMenuBlocks.forEach(block => {
            const menu = block.querySelector('#election-menu');

            const menuButtonContainer = document.createElement('div');
            menuButtonContainer.classList.add('election-menu-block__button-container');

            const menuButtonContainerRendered = block.querySelector('.election-menu-block__button-container');

            if (!menuButtonContainerRendered) {
              const button = document.createElement('button');
              button.setAttribute('type', 'button');
              button.setAttribute('aria-expanded', 'false');
              button.setAttribute('aria-controls', menu.id);
              button.innerHTML = Drupal.t('Election Menu');
              menuButtonContainer.append(button);
              block.prepend(menuButtonContainer);
              menu.setAttribute('aria-hidden', 'true');

              button.addEventListener('click', () => {
                const expanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', !expanded);
                menu.setAttribute('aria-hidden', expanded);
              });
            }
          });
        }
      }

      // This function is used to handle the menu on resize.
      // We check the window width and if it is less than 768px we set up the
      // menu. If not, we reset the menu.
      function handleElectionMenus() {
        const windowWidth = window.innerWidth;
        if (windowWidth < 768) {
          handleSetUpMenu();
        } else {
          if (electionMenuBlocks) {
            electionMenuBlocks.forEach(block => {
              const menu = block.querySelector('#election-menu');
              handleResetMenu(menu, block);
            });
          }
        }
      }

      handleElectionMenus();
      window.addEventListener('resize', Drupal.debounce(handleElectionMenus, 50, true));

    }
  };
}(Drupal));
