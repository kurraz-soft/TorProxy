<?php
require_once(dirname(__FILE__).'/TorProxy.php');

$tor = new TorProxy();

echo $tor->getPage('http://google.com');

$tor->destroy();

echo "DONE\n";
