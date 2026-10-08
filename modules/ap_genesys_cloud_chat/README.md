# Chat Bot (Lumen API) - Genesys Cloud: Chat

The chat bot feature of `ap_genesys_cloud`. It's a hidden submodule: it
has no Extend checkbox, and the parent module installs it. Setup, theming,
and the FAQ are in the parent module's `README.md`.

## What lives here
- `src/Entity/LumenGenesysDeployment.php`: the **chat deployment** config
  entity (`ap_genesys_cloud_chat.deployment.<id>`). It holds the Genesys
  environment, deployment ID, bootstrap URL, lead-capture fields, and
  branding.
- `src/Form/`: each chat deployment's **Deployment** tab
  (`LumenGenesysDeploymentForm`, including Import from Script),
  **Branding** tab (`LumenGenesysDeploymentBrandingForm`), and guarded
  delete form.
- `src/Plugin/Block/LumenChatBlock.php`: the **Chat Icon Block**. Each
  placement selects a chat deployment.
- `src/ScriptParser.php`: parses a pasted vendor script.
- `src/SvgIconSanitizer.php`: sanitizes an uploaded SVG icon.
- `src/DeploymentUsage.php`: finds the block placements that use a chat
  deployment.
- `templates/`, `js/`, `css/`, `images/`: the front-end chat icon and
  lead-capture popup, plus the Branding tab's colour-field helper.
- `tests/`: Kernel and Unit tests for all of the above.

## Admin paths
All under `admin/config/services/ap-genesys-cloud/chat`:
- the list
- `/add`
- `/manage/{id}`, the Deployment tab
- `/manage/{id}/branding`
- `/manage/{id}/delete`

## Uses from the parent module
- The **Administer Chat Bot (Lumen API) settings** permission.
- The sitewide Accessibility settings (`ap_genesys_cloud.accessibility`).
- `Drupal\ap_genesys_cloud\ColorContrast`.

This module depends on the parent, so it is never installed without them.
