![alt-text](images/azprimary-color700.png "State of Arizona")
# State of Arizona | Agency Platform - Chat Bot (Lumen API) Module

> [!NOTE]
> **Drupal Version Support**: 9

Agency Platform's Chat Bot (Lumen API) module is to provide Arizona State Agency sites an easy and more accessible solution to integrate the AZNET II provided Genesys service.

## Installing the Module
### Method: Composer (Recommended)
1. In the composer project, run `State-of-Arizona/ap_chatbot_lumen:^1.1'`
2. Turn on the module at `admin/modules` page.

### Method: Manual/SFTP
_Please note, this method will require you to return periodically for updates, if any are made._
1. Download the zip file from Code > Download Zip.
2. Extract it locally.
3. Connect to your site through SFTP or git repository.
4. Add the extracted folder to `web/modules/custom` folder, or where you currently store custom Drupal modules relative to your codebase.
5. Turn on the module at `admin/modules` page.

## Using the Module
> AZNET II will provide the agency a file named `BSDChatv#.html`, we do not need the entire file for this module, only certain bits of information that is valuable to the agency directly.

1. After enabling the module, navigate to the configuration page `admin/config/agencyplatform/chatbot-lumen` or `Configuration > Web Services > Chat Bot (Lumen API)`.
2. Enter the Environment Name and Deployment ID provided by AZNET II. This is typically found in the provided file on or around line 56 & 57. It is an early page javascript inclusion done at the head element. It will be labeled `environment` and `deploymentID` respectively. Do not copy the quotations (").
3. A default chat icon is provided, but it is possible to upload custom image (PNG, JPG, or SVG) under "Chat Icon" to replace the default icon.
4. Under "Chat Bot Fields", add in the list of fields needed for your form collection. Currently, we support Text and Select inputs. You will need a label, field ID, and Mapping Key (plus options if you choose "Select"). Your fields can be found on the file provided by AZNET II, starting after line 68 within the `<form>` container element.
   1. Field Type is Text (when you want a user to enter text, such as their name) or Select (when you want them to choose from a pre-existing list). 
   2. Label is the visible label name that is above your field you'd like for it to display. It will also be used programmatically for accessible text with "invalid-feedback" when users do not enter in data on a required field.
   3. Field ID is the equivalent of the data set in `input name=""` or `label for=""` related to the equivalent field.
   4. Mapping Key is associated with the last javascript area of the file provided for these input field you're adding. You can find these with the most bottom `<script>` section, it will be labeled under "customAttributes" as an array list and should match to your field IDs.
   5. Options should be empty for "Text" field types, but filled for "Select" field types. You will want to add your list items in comma-separated form. For example: `Arizona, New Mexico, Texas, Utah`.
   6. If you need to reorder the list of fields, choose "show row weights" on the top right of the section and arrange the list this way.

**In most cases, you will only need to return to edit the fields when you are provided a new version of your script. If there is a styling difference that is apparent, please provide the information to the Agency Platform team to help update the module.**

5. Navigate to `/admin/structure/block` or `Structure > Block Layout`.
6. Use any visible region of your website (Footer or Header placement is fine), and choose "Place Block" button next to the relative region choosen.
7. Look for "Chat Icon Block" from the list of blocks provided and choose "Place block".
8. Click "Save" to allow it to appear on all pages.
   1. Should you need to customize which pages or content types, or even roles, are allowed to see this block appear, you are welcome to modify that restriction in this window before you save the block.
9. Clear site cache.

## FAQ & Common Issues
### Will this be part of the Agency Platform Distribution?
The ultimate goal is to add this module to our existing distribution. We are currently testing it on some sites to verify consistency before we add it. It will be a future feature!
### Where can I submit a bug, issue, or feedback report?
For those part of the OKTA single sign on, create a ticket for [Agency Platform at ServiceNow](https://azdoaprod.servicenowservices.com/esc?id=sc_cat_item&sys_id=3f1dd0320a0a0b99000a53f7604a2ef9). Be sure to mark the category as "Agency Platform Website". Otherwise, connect with the state help desk for further assistance to connecting with the Agency Platform team. Our team also welcomes developer feedback and reports on the [repository's issues page](https://github.com/State-of-Arizona/ap_chatbot_lumen/issues) via Github.