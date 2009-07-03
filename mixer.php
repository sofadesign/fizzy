<?php
// Take some limonade
require_once 'lib/limonade.php';
// Add the Fizzy
require_once 'lib/fizzy.php';

define('ROOT_DIR', realpath('../'));
define('CONFIG_FILE', ROOT_DIR . '/config.xml');
define('PAGES_FILE', ROOT_DIR . '/pages.xml');
define('VIEWS_DIR', ROOT_DIR . '/views/');
define('PUBLIC_DIR', ROOT_DIR . '/public/');
define('BASE_URL', '');

// shake it
shake();
// Fizzy Limonade!
