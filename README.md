# CNHSA Content Federation

This plugin creates a two-way street, in conjunction with its sibling plugin, for federating content between the CNO and CNHSA sites. It is exclusively for use on the Choctaw Nation site.

## Quick Dev Notes

-   This plugin uses classmap autoloading, meaning **each time you add a new class, you must run `composer dump-autoload` to load it.**
-   Classes are namespaced in PSR-4-like style. It's not perfect, but it basically conforms to the following structure:
    -   `namespace ChoctawNation\CNHSA_Federation` points to `inc/`
    -   Every folder after that is included in the namespace (i.e. `namespace ChoctawNation\CNHSA_Federation\Transport`)

## Architecture Overview

### General Overview:

-   `inc/` is where PHP lives
-   `tests/` is where the PHPUnit tests live
-   `src/` is where the React code (for Admin Screen) lives

### Terms

-   `inc/WP` has files (and subdirectories) related to WordPress actions (hooks, cron events, post/payload creation, etc)
-   `inc/Transport` is for files related to sending/receiving data over HTTP
    -   `HTTP_Gateway` class is for sending (outbound) data
    -   `Rest_Router` class is for receiving (inbound) data
