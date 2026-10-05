<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin SPA Translation Namespaces
    |--------------------------------------------------------------------------
    |
    | Maps every i18next namespace used by the admin SPA to its backend
    | language group and the owner that stores it. `module` points at an
    | nwidart module whose `lang/` directory holds the files; omitting it keeps
    | the group in the application `resources/lang` directory.
    |
    | Module namespaces are registered explicitly (independently of module
    | activation) so the translations endpoint keeps working even when the
    | owning module is disabled.
    |
    */

    'namespaces' => [
        'common' => ['group' => 'common'],
        'admin' => ['group' => 'admin', 'module' => 'Admin'],
        'auth' => ['group' => 'admin_auth', 'module' => 'Auth'],
        'blog' => ['group' => 'blog', 'module' => 'Blog'],
        'network' => ['group' => 'network', 'module' => 'Network'],
    ],

];
