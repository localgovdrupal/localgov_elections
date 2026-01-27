<?php

declare(strict_types=1);

namespace Drupal\localgov_elections_configurable_provider;

use GuzzleHttp\Client;

/**
 * Helper class for REST API interactions.
 *
 * Provides static utility methods for making API queries,
 * extracting results from responses, and normalising data formats.
 */
class ApiHelper {

  /**
   * Replace {token} placeholders in a string with values.
   *
   * @param string $template
   *   The template string with {token} placeholders.
   * @param array $values
   *   Associative array of token => value replacements.
   *
   * @return string
   *   The string with tokens replaced.
   */
  public static function replaceTokens(string $template, array $values): string {
    foreach ($values as $key => $value) {
      $template = str_replace('{' . $key . '}', (string) $value, $template);
    }
    return $template;
  }

  /**
   * Execute a REST API query.
   *
   * Builds query parameters in the ArcGIS REST API style and returns
   * the decoded JSON response body. The caller is responsible for
   * extracting results from the response.
   *
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param string $base_url
   *   The full API query endpoint URL.
   * @param string $where
   *   The where clause (already with tokens replaced).
   * @param string $out_fields
   *   Comma-separated field list or *.
   * @param string $format
   *   Response format: 'json' or 'geojson'.
   * @param bool $return_geometry
   *   Whether to request geometry data.
   * @param array $additional_params
   *   Any additional query parameters to merge.
   *
   * @return array
   *   The decoded JSON response body.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP request fails.
   */
  public static function executeQuery(
    Client $httpClient,
    string $base_url,
    string $where,
    string $out_fields = '*',
    string $format = 'json',
    bool $return_geometry = FALSE,
    array $additional_params = [],
  ): array {
    $params = [
      'query' => array_merge([
        'where' => $where,
        'outFields' => $out_fields,
        'returnDistinctValues' => 'true',
        'returnGeometry' => $return_geometry ? 'true' : 'false',
        'outSR' => '4326',
        'f' => $format,
        'resultRecordCount' => 10000,
      ], $additional_params),
    ];

    $response = $httpClient->get($base_url, $params);

    if ($response->getStatusCode() !== 200) {
      return [];
    }

    $body = $response->getBody()->getContents();
    $decoded = json_decode($body, TRUE);

    return $decoded ?? [];
  }

  /**
   * Extract results array from an API response using a dot-notation path.
   *
   * @param array $response
   *   The decoded API response.
   * @param string $result_path
   *   Dot-notation path to the results array (e.g. 'features' or 'data.items').
   *
   * @return array
   *   The extracted results array, or empty array if path not found.
   */
  public static function extractResults(array $response, string $result_path): array {
    if (empty($result_path)) {
      return $response;
    }

    $parts = explode('.', $result_path);
    $current = $response;

    foreach ($parts as $part) {
      if (!is_array($current) || !isset($current[$part])) {
        return [];
      }
      $current = $current[$part];
    }

    return is_array($current) ? $current : [];
  }

  /**
   * Get an attribute value from a result item.
   *
   * Handles both flat structures (attribute at top level) and nested
   * structures (attribute within a sub-key like 'attributes' or 'properties').
   *
   * @param array $item
   *   The result item array.
   * @param string $attribute_name
   *   The attribute name to retrieve.
   * @param string $attributes_path
   *   Path to the attributes within the item. Empty string for flat structure,
   *   'attributes' for ArcGIS JSON, 'properties' for GeoJSON.
   *
   * @return mixed
   *   The attribute value, or NULL if not found.
   */
  public static function getAttributeValue(array $item, string $attribute_name, string $attributes_path): mixed {
    if (empty($attributes_path)) {
      return $item[$attribute_name] ?? NULL;
    }

    $parts = explode('.', $attributes_path);
    $current = $item;

    foreach ($parts as $part) {
      if (!is_array($current) || !isset($current[$part])) {
        return NULL;
      }
      $current = $current[$part];
    }

    return $current[$attribute_name] ?? NULL;
  }

  /**
   * Normalize a feature to GeoJSON Feature format.
   *
   * When the API returns JSON format (f=json), the response uses ArcGIS JSON
   * format with 'attributes' and 'geometry' keys. This converts it to standard
   * GeoJSON Feature format for storage. GeoJSON features are passed through
   * unchanged.
   *
   * @param array $feature
   *   The feature in either format.
   * @param string $source_format
   *   Either 'json' or 'geojson'.
   *
   * @return array
   *   A GeoJSON Feature array.
   */
  public static function normalizeToGeoJson(array $feature, string $source_format): array {
    if ($source_format === 'geojson') {
      return $feature;
    }

    // Convert ArcGIS JSON to GeoJSON.
    $geojson = [
      'type' => 'Feature',
      'properties' => $feature['attributes'] ?? [],
      'geometry' => NULL,
    ];

    $geometry = $feature['geometry'] ?? [];
    if (!empty($geometry)) {
      if (isset($geometry['rings'])) {
        // Polygon or MultiPolygon.
        if (count($geometry['rings']) > 1) {
          $geojson['geometry'] = [
            'type' => 'MultiPolygon',
            'coordinates' => array_map(fn($ring) => [$ring], $geometry['rings']),
          ];
        }
        else {
          $geojson['geometry'] = [
            'type' => 'Polygon',
            'coordinates' => $geometry['rings'],
          ];
        }
      }
      elseif (isset($geometry['paths'])) {
        // LineString or MultiLineString.
        if (count($geometry['paths']) > 1) {
          $geojson['geometry'] = [
            'type' => 'MultiLineString',
            'coordinates' => $geometry['paths'],
          ];
        }
        else {
          $geojson['geometry'] = [
            'type' => 'LineString',
            'coordinates' => $geometry['paths'][0],
          ];
        }
      }
      elseif (isset($geometry['x']) && isset($geometry['y'])) {
        // Point.
        $geojson['geometry'] = [
          'type' => 'Point',
          'coordinates' => [$geometry['x'], $geometry['y']],
        ];
      }
    }

    return $geojson;
  }

  /**
   * Build a token values map from a filters configuration array.
   *
   * @param array $filters
   *   The filters array from plugin configuration.
   *
   * @return array
   *   Associative array of filter key => filter value.
   */
  public static function buildTokenMap(array $filters): array {
    $tokens = [];
    foreach ($filters as $filter) {
      if (!empty($filter['key']) && isset($filter['value'])) {
        $tokens[$filter['key']] = $filter['value'];
      }
    }
    return $tokens;
  }

  /**
   * Parse additional parameters from a key=value string.
   *
   * @param string $params_string
   *   Parameters as key=value pairs, one per line.
   *
   * @return array
   *   Associative array of parsed parameters.
   */
  public static function parseAdditionalParams(string $params_string): array {
    $params = [];
    if (empty($params_string)) {
      return $params;
    }

    $lines = preg_split('/\r\n|\r|\n/', $params_string);
    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || !str_contains($line, '=')) {
        continue;
      }
      [$key, $value] = explode('=', $line, 2);
      $key = trim($key);
      $value = trim($value);
      if (!empty($key)) {
        $params[$key] = $value;
      }
    }

    return $params;
  }

}
