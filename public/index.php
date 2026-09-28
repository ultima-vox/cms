safeLoad();

$(httpMethod =\)_SERVER['REQUEST_METHOD'];
$(uri =\)_SERVER['REQUEST_URI'];

Router::dispatch($(httpMethod,$)uri);