(function (Drupal, drupalSettings) {
  'use strict';

  const deploymentId = drupalSettings.apGenesysCloud?.deploymentId;
  const environmentName = drupalSettings.apGenesysCloud?.envName;
  const bootstrapUrl = drupalSettings.apGenesysCloud?.bootstrapUrl;

  // Each Chat Icon Block adds its deployment ID here. drupalSettings from
  // every block on the page are merged into one object, so more than one
  // key means two placements with different deployments are visible on
  // this page and the values above are a mix of both. Only one Genesys
  // deployment can run per page, so refuse to start rather than load the
  // wrong one.
  const instances = Object.keys(
    drupalSettings.apGenesysCloud?.instances || {},
  ).filter((id) => id !== '');

  if (instances.length > 1) {
    // eslint-disable-next-line no-console
    console.error(
      `Genesys Messenger initialization skipped: more than one chat deployment is placed on this page (${instances.join(', ')}). Adjust the Chat Icon Block visibility settings so only one deployment appears per page.`,
    );
  }
  else if (deploymentId && environmentName && bootstrapUrl) {
    // Genesys-provided bootstrap snippet. Queues commands on window.Genesys
    // until the real SDK script (loaded async below) replaces it.
    (function (g, e, n, es, ys) {
      g['_genesysJs'] = e;
      g[e] =
        g[e] ||
        function () {
          (g[e].q = g[e].q || []).push(arguments);
        };
      g[e].t = 1 * new Date();
      g[e].c = es;
      ys = document.createElement('script');
      ys.async = 1;
      ys.src = n;
      ys.charset = 'utf-8';
      document.head.appendChild(ys);
    })(window, 'Genesys', bootstrapUrl, {
      environment: environmentName,
      deploymentId: deploymentId,
    });

    // Clear any stale custom attributes from a previous visit.
    document.addEventListener('DOMContentLoaded', () => {
      Genesys('command', 'Database.set', {
        messaging: {
          customAttributes: {},
        },
      });
    });
  }
  else {
    // eslint-disable-next-line no-console
    console.error('Genesys Messenger initialization failed: missing deploymentId, environmentName, or bootstrapUrl. Configure the Genesys Cloud/API settings.');
  }
})(Drupal, drupalSettings);
