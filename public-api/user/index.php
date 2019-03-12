<?php

require_once __DIR__."/vendor/Autoloader.php";
require_once __DIR__."/MinimalRouter.php";

$autoloader->setup(__DIR__."/lib");

$router = new Router(__DIR__."/routes");

$router->add_route("/avatar/\d+/[s|m|l]", "/Avatar.php");

if (!$router->handle_request())
{
    $router->throw(StatusCode::NOT_FOUND, "not found");

    exit;
}

?>