# Changelog

## v1.1.2 - [March 10, 2026]

-   Fixed: ID Resolution uses Title instead of slug (slugs may differ across sites)
-   Fixed: Publishing a service with a location also updates location's `cnhsa_id` meta field

## v1.1.1

-   Fixed: ID Lookup now uses proper base_url
-   Refactored: `Publisher::update_{$post_type}` logic refactored for more clarity in workflow
-   Fixed: Tests updated to more accurately mock HTTP Response objects

## v1.1.0 - [March 6, 2026]

-   Added: Locations now send along a link to their "featured image"
-   Fixed: Properly loads external links for 'related media' ACF fields

## v1.0.0

-   Init!
