<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module name
    |--------------------------------------------------------------------------
    */

    'name' => 'Network',

    /*
    |--------------------------------------------------------------------------
    | Main Website ID
    |--------------------------------------------------------------------------
    |
    | The UUID of the main website record. It is used to resolve the main site
    | when running in console and to detect whether the current request is
    | being served by the main website.
    |
    */

    'main_website_id' => env('NETWORK_MAIN_WEBSITE_ID', '00000000-0000-4000-8000-000000000000'),

    /*
    |--------------------------------------------------------------------------
    | Network Domain
    |--------------------------------------------------------------------------
    |
    | The primary domain that serves the main website.
    |
    */

    'domain' => env('NETWORK_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Subsite Domain
    |--------------------------------------------------------------------------
    |
    | The base domain used to resolve subsites by their subdomain, e.g.
    | "{subdomain}.example.com".
    |
    */

    'subsite_domain' => env('NETWORK_SUBSITE_DOMAIN'),

];
