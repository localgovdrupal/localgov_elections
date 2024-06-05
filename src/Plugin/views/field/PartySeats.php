<?php

namespace Drupal\localgov_elections_reporting\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to flag the node type.
 *
 * @ViewsField("party_seats")
 */
class PartySeats extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query() {

  }

  /**
   * Render function for the part_seats.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values) {

    return 3;
  }

}
