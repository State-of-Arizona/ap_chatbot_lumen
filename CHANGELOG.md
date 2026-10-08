# Changelog
All notable changes specific to `State-of-Arizona/ap_genesys_cloud` from Agency Platform (Arizona ASET Digital Government) are noted here.

## v2.0.0 | 2026-10-07
### Changed/Fixed
### Changed/Added/Fixed
- Fully revamped the module after testing with various instances of use
  - Ready for Drupal 11 use
  - Supports Genesys handled and site handled launchers
  - Added ability to define form accessible 
  - Allows more than one instance of a chatbot block for a single site (but still needs to be one bot per page)
  - Added a script parser to make it easier to pull out important script needs to run a chatbot
  - Added a WCAG accessibility warning/checker for manually changing branding colors
  - Added the ability for branding to be controlled by the site's theme, if the root css property is defined
  - Added separate administrative permission to manage the module
  - Added a helper context page
  - Prepared the module to extend beyond just a chatbot
- Module is renamed to be more generic if non State of Arizona use is obtained
- Created `CHANGELOG.md` to track specific project changes for each version release
### Removed
- Version 1.x.x files reflecting former naming convention and solution


## v1.1.0 | 2025-02-27
Released initial version of the Agency Platform Drupal module.