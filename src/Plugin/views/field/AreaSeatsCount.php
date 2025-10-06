<?php

namespace Drupal\localgov_elections\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to display the count of seats in an area.
 *
 * @ViewsField("area_seats_count")
 */
class AreaSeatsCount extends FieldPluginBase {

  /**
   * Leave empty to avoid a query on this field.
   */
  public function query(): void {

  }

  /**
   * Render function for the area seats count.
   *
   * @{inheritdoc}
   */
  public function render(ResultRow $values): int {
    $node = $this->getEntity($values);

    if (!$node || !$node->hasField('localgov_election_seats')) {
      return 0;
    }

    return $node->get('localgov_election_seats')->count();
  }

}
