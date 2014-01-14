<?php

require_once 'php_hybrid_sessions.inc.php';

session_start();

if(!array_key_exists('v',$_SESSION)) {
  echo "new session. ";
  $_SESSION['v'] = "session started at ".time();
}

echo $_SESSION['v'];

