<?php

namespace Drupal\localgov_elections_reporting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("party_abbr")
 */
class PartyAbbr extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the party_abbr field.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

    // @todo Get rid
    return "IND";
  }

}
