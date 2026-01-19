/**
 * @file
 * Multi-winner constituency visualisation using horizontal stripes.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.electoralMapBoundaries = {
    attach: function (context, settings) {
      if (!settings.electoralMapData) {
        return;
      }

      // Process immediately if map exists, otherwise wait briefly.
      if (typeof Drupal.leaflet !== 'undefined' && Object.keys(Drupal.leaflet).length > 0) {
        processElectoralMapData(settings.electoralMapData);
      } else {
        setTimeout(function() {
          processElectoralMapData(settings.electoralMapData);
        }, 50);
      }
    }
  };

  /**
   * Process electoral map data and apply visualisations.
   */
  function processElectoralMapData(mapData) {
    let mapContainer = document.getElementById('leaflet-map-view-localgov-election-electoral-map-page-map');
    if (!mapContainer) {
      return;
    }

    let $mapContainer = $(mapContainer);
    let leafletData = $mapContainer.data('leaflet');

    if (!leafletData || !leafletData.lMap) {
      return;
    }

    let map = leafletData.lMap;

    // Prevent multiple processing.
    if (map._electoralMapProcessed) {
      return;
    }
    map._electoralMapProcessed = true;

    // Process each electoral area.
    Object.keys(mapData).forEach(function(nodeId) {
      let data = mapData[nodeId];

      if (data.winners.length === 1) {
        // Single winner - solid color.
        createSingleWinnerOverlay(map, JSON.parse(data.boundary), data.winners[0].color);
      } else {
        // Multiple winners - horizontal stripes.
        createStripeOverlays(map, data);
      }
    });

    // Ensure boundary layers appear on top of overlays.
    setTimeout(function() {
      bringBoundariesToFront(map);
    }, 20);
  }

  /**
   * Create overlay for single-winner constituency with party colour.
   */
  function createSingleWinnerOverlay(map, feature, partyColour) {
    let overlayLayer = L.geoJSON(feature, {
      style: {
        fillColor: partyColour,
        color: '#000000',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.7,
        interactive: false,
        stroke: true
      }
    });

    overlayLayer.addTo(map);
  }

  /**
   * Bring boundary layers to the front.
   */
  function bringBoundariesToFront(map) {
    map.eachLayer(function(layer) {
      if (layer.setStyle && layer.options && layer.options.weight > 0 && layer.options.fillOpacity === 0) {
        if (layer.bringToFront) {
          layer.bringToFront();
        }
      }
    });
  }

  /**
   * Create horizontal stripe overlays for a multi-winner constituency.
   */
  function createStripeOverlays(map, data) {
    try {
      let boundary = JSON.parse(data.boundary);
      let numWinners = data.winners.length;

      data.winners.forEach(function(winner, index) {
        let boundaryLayer = L.geoJSON(boundary, {
          style: {
            fillColor: winner.color,
            color: '#000000',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.7,
            interactive: false,
            stroke: true
          }
        });

        // Apply CSS clipping to show only this winner's stripe.
        boundaryLayer.on('add', function() {
          setTimeout(function() {
            let pathElement = findPathElement(boundaryLayer);

            if (pathElement) {
              // Calculate horizontal stripe boundaries.
              let topPercent = (index / numWinners) * 100;
              let bottomPercent = ((index + 1) / numWinners) * 100;

              // Apply CSS clip-path to show only this horizontal stripe.
              let clipPath = `polygon(0% ${topPercent}%, 100% ${topPercent}%, 100% ${bottomPercent}%, 0% ${bottomPercent}%)`;
              pathElement.style.clipPath = clipPath;
            }
          }, 20);
        });

        boundaryLayer.addTo(map);
      });

    } catch (error) {
      console.error('Error creating stripe overlays:', error);
    }
  }

  /**
   * Find the SVG path element within a Leaflet layer.
   */
  function findPathElement(boundaryLayer) {
    let pathElement = null;

    if (boundaryLayer.eachLayer) {
      boundaryLayer.eachLayer(function(subLayer) {
        if (subLayer._path && !pathElement) {
          pathElement = subLayer._path;
        }
      });
    }

    return pathElement;
  }

})(jQuery, Drupal, drupalSettings);
