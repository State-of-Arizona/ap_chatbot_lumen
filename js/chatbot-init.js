(function (Drupal, drupalSettings) {
  const deploymentId = drupalSettings.apChatbotLumen?.deploymentId;
  const environmentName = drupalSettings.apChatbotLumen?.envName;

  if (deploymentId && environmentName) {
    // Initialize Genesys Messenger
    (function (g, e, n, es, ys) {
      g["_genesysJs"] = e;
      g[e] =
        g[e] ||
        function () {
          (g[e].q = g[e].q || []).push(arguments);
        };
      g[e].t = 1 * new Date();
      g[e].c = es;
      ys = document.createElement("script");
      ys.async = 1;
      ys.src = n;
      ys.charset = "utf-8";
      document.head.appendChild(ys);
    })(
      window,
      "Genesys",
      "https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js",
      {
        environment: environmentName,
        deploymentId: deploymentId,
      }
    );

    // Clear custom attributes on page load
    document.addEventListener("DOMContentLoaded", () => {
      if (window.Genesys) {
        Genesys("command", "Database.set", {
          messaging: {
            customAttributes: {},
          },
        });
        console.log("Genesys custom attributes reset.");
      }
    });
  } else {
    console.error("Genesys Messenger initialization failed: Missing deploymentId or environmentName.");
  }
})(Drupal, drupalSettings);
