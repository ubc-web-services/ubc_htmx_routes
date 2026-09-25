# HTMX Routes

This module adds custom htmx routes for content entities and defines the view mode those routes should use. 

The intended use is to provide a new custom route and specific view mode for content so it can be loaded via htmx without surrounding structural layout.

## Usage
Go to Configuration > Content Authoring > UBC HTMX Routes settings

Enable entity types and assign a view mode

Routes will be available at:

/htmx/[entity-type]/[entity-id]

Ensure the htmx library is loaded in your [theme/module].libraries.yaml
```
dependencies:
    - core/htmx
```

## HTMX docs
htmx is a library (included in Drupal core) that allows you to access modern browser features directly from HTML, rather than using javascript.
(https://htmx.org/docs/)[https://htmx.org/docs/]
