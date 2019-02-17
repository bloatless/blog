<?php

require __DIR__ . '/../src/Parsedown.php';
require __DIR__ . '/../src/Blog.php';

$pathConfig = __DIR__ . '/../config/config.php';
$pathDefaultConfig = __DIR__ . '/../config/config.default.php';

if (file_exists($pathConfig)) {
    $config = include $pathConfig;
} elseif (file_exists($pathDefaultConfig)) {
    $config = include $pathDefaultConfig;
}

if (!isset($config)) {
    exit('Could not find valid config file.');
}


$app = new \Bloatless\Blog\Blog($config);
$app->__invoke();
