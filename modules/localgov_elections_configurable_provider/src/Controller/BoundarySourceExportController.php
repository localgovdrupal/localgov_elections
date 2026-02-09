<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\localgov_elections\Entity\BoundarySource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for exporting boundary source configurations.
 */
class BoundarySourceExportController extends ControllerBase {

  /**
   * Exports a boundary source configuration as YAML.
   *
   * @param \Drupal\localgov_elections\Entity\BoundarySource $boundary_source
   *   The boundary source entity to export.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response containing the YAML export.
   */
  public function export(BoundarySource $boundary_source): Response {
    // Only export configurable provider sources.
    if ($boundary_source->get('plugin') !== 'configurable_provider') {
      return new Response(
        $this->t('Only Configurable Provider boundary sources can be exported.')->render(),
        Response::HTTP_BAD_REQUEST
      );
    }

    $settings = $boundary_source->getSettings();

    // Clear filter values and remove empty filter slots.
    if (!empty($settings['filters'])) {
      $cleaned_filters = [];
      foreach ($settings['filters'] as $filter) {
        if (!empty($filter['key'])) {
          $filter['value'] = '';
          $cleaned_filters[] = $filter;
        }
      }
      $settings['filters'] = $cleaned_filters;
    }

    $export = [
      'label' => $boundary_source->label(),
      'description' => $boundary_source->get('description') ?? '',
      'settings' => $settings,
    ];

    $yaml = Yaml::encode($export);

    // Add a header comment.
    $header = "# Boundary Source Configuration Export\n";
    $header .= "# Exported from: " . $boundary_source->label() . "\n";
    $header .= "#\n";
    $header .= "# To import: Go to Admin > Structure > Boundary Sources > Import configuration\n";
    $header .= "# After importing, update the filter values for your local authority.\n";
    $header .= "#\n";

    $yaml = $header . $yaml;

    $response = new Response($yaml);
    $response->headers->set('Content-Type', 'application/x-yaml');
    $response->headers->set('Content-Disposition', 'attachment; filename="boundary-source-' . $boundary_source->id() . '.yml"');

    return $response;
  }

  /**
   * Title callback for the export page.
   *
   * @param \Drupal\localgov_elections\Entity\BoundarySource $boundary_source
   *   The boundary source entity.
   *
   * @return string
   *   The page title.
   */
  public function exportTitle(BoundarySource $boundary_source): string {
    return $this->t('Export @label', ['@label' => $boundary_source->label()])->render();
  }

}
