![alt-text](images/azprimary-color700.png "State of Arizona")
# State of Arizona | Agency Platform - Genesys Cloud Module

> [!NOTE]
> **Drupal Version Support**: 9, 10, 11

Agency Platform's Genesys Cloud module is to provide Arizona State Agency sites an easy and more accessible solution to integrate the AZNET II provided Genesys chatbot service to their Drupal site. It gives a site an easy, consistent, and accessible way to integrate a Lumen/Genesys-provided chat widget, without hand-editing a `<script>` tag into a WYSIWYG editor on every site that needs it. A site can have one or more instances of the chatbot, but there should only be one instance of the chatbot per page.


## Installing the Module
### Method: Composer (Recommended)
1. Add `drupal-ap-module` as a composer installer-path to the site's root composer file, if it does not exist already.
   1. If using the Agency Platform distribution and on Drupal 11, this will already be done
   2. If not using the Agency Platform distribution, or on Drupal 9, the path can simply be `web/modules/custom/{$name}` or folder of choosing.
2. In the composer project, run 
   1. `composer require state-of-arizona/ap_genesys_cloud:^2.0'` for production ready releases
   2. `composer require state-of-arizona/ap_genesys_cloud:^2.0@dev` for development testing releases
3. Turn on the module at `admin/modules` page.

### Method: Manual/SFTP
_Please note, this method will require you to return periodically for updates, if any are made._
1. Install the library `enshrined/svg-sanitize` (1.0.0 or newer)
2. Download this module's zip file from Code > Download Zip.
3. Extract it locally.
4. Connect to your site through SFTP or git repository.
5. Add the extracted folder to `web/modules/custom` folder, or where you currently store custom Drupal modules relative to your codebase.
6. Turn on the module at `admin/modules` page.

## Using the Module
> AZNET II will provide the agency a file named `BSDChatv#.html`, we do not need the entire file for this module, only certain bits of information that is valuable to the agency directly.

Everything about one Genesys deployment -- its IDs, its lead-capture form, and how its chat icon looks -- lives on a **chat deployment**. Most sites need just one. Sites with a different deployment per area of the site add one per area (see below).

1. After enabling the module, go to `admin/config/services/ap-genesys-cloud` (`Configuration > Web Services > Genesys Cloud/API Settings`) and open **Chat deployments** (`admin/config/services/ap-genesys-cloud/chat`). Click **Add chat deployment** and give it a label (e.g. the area it serves, or just "Default").
2. **Easiest path -> let the module read the file for you:** open **Import from Script**, paste the *entire* file the vendor provided, and click **Parse Script**. It extracts the Environment Name, Deployment ID, Bootstrap Script URL, and (best-effort) the lead-capture form's fields and their mapping keys, and fills them into the fields above for you to review. Nothing is saved until you click **Save** -- check the values, fix anything the parse couldn't figure out (it'll tell you what it missed), then save. This is meant for exactly the situation where nobody on hand wants to go read JavaScript by hand.
3. **Or enter values by hand**, if you'd rather (or the parse missed something):
    1. **Environment Name** and **Deployment ID** -- look for the early `<script>` block in `<head>`, labeled `environment` and `deploymentId`. Don't copy the surrounding quotes.
    2. **Genesys Bootstrap Script URL** -- from that same `<script>` block; it points at a specific Genesys region/environment, which varies by vendor and deployment, so there's no default value. It must be an HTTPS Genesys Cloud address, e.g. `https://apps.use2.us-gov-pure.cloud/genesys-bootstrap/genesys.min.js`: any `mypurecloud.*`, `pure.cloud`, `us-gov-pure.cloud` or `eusc-pure.cloud` host. If Genesys adds a region on a new domain, a developer can allow it in `settings.php` with `$settings['ap_genesys_cloud_chat_bootstrap_domains'] = ['new-domain.example'];`. This is deliberately not an admin setting.
    3. Under **Chat Bot Fields**, add the fields your lead-capture form should collect (already pre-filled if Parse Script found a form), matching the `<form>` in the provided file:
        1. **Field Type**: Text or Select.
        2. **Label**: the visible label; also used for accessible "required field" messaging.
        3. **Field ID**: matches the field's `id`/`name` in the provided file.
        4. **Mapping Key**: matches the corresponding key in that file's bottom `<script>` block, under `customAttributes`.
        5. **Autocomplete** (text fields only): for a field asking for the visitor's own details, the standard HTML autocomplete value. For example `name` for a full name, `given-name`/`family-name` for first/last name, `email`, or `tel`. This lets browsers and assistive technology identify the field's purpose (WCAG 2.1 SC 1.3.5), and gives email/phone fields the right input type. Leave it blank for anything else, such as an ID or licence number, and it stays `autocomplete="off"`. Only values from the HTML standard are accepted; password, one-time-code and payment-card values aren't allowed. Import from Script doesn't fill this in.
        6. **Options**: comma-separated, only for Select fields (e.g. `Sales, Support, Billing`).
        7. Reorder rows with "Show row weights."

       **If the provided file has no `<form>` at all** (just the bootstrap `<script>` block), leave "Chat Bot Fields" empty. Clicking the chat icon will open the Genesys Messenger directly instead of showing a lead-capture popup first.
4. Saving a new chat deployment takes you to its **Branding** tab. Set **Button & Header Color** and **Icon & Text Color** to the brand colours for that deployment (these apply to the default icon, popup header, and submit button - an uploaded custom icon keeps its own colours), and optionally upload a custom **Chat Icon** (PNG, JPG, or SVG). The two colours are checked for contrast against each other; the sitewide **Accessibility** settings (listed next to **Chat deployments** on the landing page) control how strictly that's enforced for every chat deployment (off, warn, or block Save; targeting WCAG AA or AAA). If your theme should control the styling instead, see **Theming** below.

**In most cases, you'll only revisit a chat deployment when your chat vendor sends a new version of your script.** If there's a styling difference, let whoever maintains this module for your site know, so it can be updated where applicable.

5. Navigate to `Structure > Block Layout` (`admin/structure/block`).
6. Place the **Chat Icon Block** in any visible region (Footer or Header both work), and select its **Chat deployment** -- this is required. 
7. By default, the block appears on every page. To restrict it, use the block's own **Pages** visibility condition before saving.
8. Clear the site cache.

### Using a different deployment in different areas of the site
Some agencies have more than one Genesys deployment, for example one per program area, each with its own environment, bootstrap URL, lead-capture form, and look.

1. Add one chat deployment per area, as above. Each has its own **Deployment** and **Branding** tabs.
2. In **Block Layout**, place one **Chat Icon Block** per area. In each block's settings, pick its **Chat deployment** and use the block's **Visibility** settings (pages, content types, roles) to choose where it appears.
3. **Only one deployment can run on a page.** Genesys's bootstrap script supports a single deployment per page. Make sure placements with different deployments are never visible on the same page; a sitewide block usually needs those areas' paths excluded (Pages → "Hide for the listed pages"). If two different deployments do end up on one page, the module logs a console error and loads neither, rather than mixing their settings and lead-capture fields.
4. A chat deployment can't be deleted while any Chat Icon Block placement still uses it. Its delete page lists those blocks. (Drupal would otherwise delete those block placements along with it.) The **Used by blocks** column on the Chat deployments list shows the same thing.

Genesys keeps each deployment's conversation separately, so a visitor who moves from one area to another sees that area's own chat, not the conversation they started elsewhere.

## Theming

A chat deployment's Branding tab colour fields are the easiest way to set the chat icon/popup's colours, but a theme can also take over styling directly:

- **CSS custom properties**: the chat icon and popup read their colours from `--apgc-brand-color` and `--apgc-text-color` (see `css/chat.css`). If your theme's own CSS defines these on `:root` or higher up the DOM than the chat block, it wins over anything set here - or check **Use theme default colors instead of the settings below** on the chat deployment's Branding tab, which stops this module from injecting its own colour override at all, leaving your theme's CSS (or `chat.css`'s own neutral fallback, if your theme sets nothing) in full control.
- **Additional CSS Class**: set a class in the **Additional CSS Class** field on a chat deployment's Branding tab and it's added to both the chat trigger (`#apgc-chat`) and the popup (`#apgc-chat-popup`), so a theme developer who'd rather write plain CSS than learn the custom properties above can just target that class directly.
- **Existing structural classes**, if you need more granular hooks than the two above: `.apgc-chatbubble` (trigger), `.apgc-popup` (popup), `.apgc-popup__header` (popup header bar), `.apgc-form__submit` (submit button) - these already exist and aren't going anywhere, but aren't independently configurable the way the two options above are.

## Permissions
This module defines its own **Administer chatbot (Genesys Cloud) settings** permission, so it can be delegated to individual site admins without also granting them full site configuration access.


## FAQ & Common Issues
### I see two chat buttons — mine, and a default Genesys one on top of it
This is a Genesys Cloud deployment setting, not something this module controls. Genesys's own bootstrap script shows its default launcher button unless the deployment is configured to suppress it. Ask whoever administers that Genesys Cloud deployment to set **Appearance and Branding → Launcher Visibility** to **"Hide"** for this deployment. This module's Chat Icon block will keep working as the only launcher once that's set.

While you're waiting on that to happen (or if a chat vendor hands off a script before confirming the launcher's hidden), check **"Let Genesys Cloud manage the chat launcher"** at the top of that chat deployment's **Branding** tab. It stops this module from rendering its own icon/popup entirely, so visitors only ever see the one Genesys launcher instead of two. Uncheck it once the launcher's actually hidden on the Genesys Cloud side.

### Will this be part of the Agency Platform Distribution?
The ultimate goal is to add this module to our existing distribution. We are currently testing it on some sites to verify consistency before we add it. It will be a future feature!
### Where can I submit a bug, issue, or feedback report?
For those part of the OKTA single sign on, create a ticket for [Agency Platform at ServiceNow](https://azdoaprod.servicenowservices.com/esc?id=sc_cat_item&sys_id=3f1dd0320a0a0b99000a53f7604a2ef9). Be sure to mark the category as "Agency Platform Website". Otherwise, connect with the state help desk for further assistance to connecting with the Agency Platform team. Our team also welcomes developer feedback and reports on the [repository's issues page](https://github.com/State-of-Arizona/ap_genesys_cloud/issues) via Github.

## AI Disclaimer
Test processes under `*/tests/*` were assisted and reviewed with AI. While they work in a closed environment, there is always possibility that additional tweaking may be necessary, something did not work expectingly, or something might have been missed to check for with normal phpcs.