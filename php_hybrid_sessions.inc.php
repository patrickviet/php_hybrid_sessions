<?php

// PHP HYBRID SESSIONS
// Session handler that gets session data from the local directory
// OR memcache (as backup)

// This session handler must be combined with a background script that
// asynchronously copies the sessions to memcache. The session handler itself
// only save the data to local disk

// phs is short for php hybrid sessions.


session_set_save_handler('phs_open', 'phs_close', 'phs_read', 'phs_write',
  'phs_destroy', 'phs_gc');

$phs_memc_host = '127.0.0.1';
$phs_memc_port = '11211';

function phs_open($path, $sess_id) {
  global $phs_path;
  $phs_path = $path;

  return TRUE;
}

function phs_read($sess_id) {
  global $phs_path;
  $sfile = "$phs_path/php_sess_$sess_id";

  if (file_exists($sfile)) {
    return file_get_contents($sfile);
  }

  // init an empty file
  touch($sfile);

  // let's see if that ID exists on memcache
  // this part can silently fail
  $memc = new Memcache;
  global $phs_memc_host;
  global $phs_memc_port;
  if(@$memc->connect($phs_memc_host,$phs_memc_port)) {
    if(($data = @$memc->get("php_sess_$sess_id")) !== FALSE) {
      // it's there. let's import it!
      file_put_contents($sfile,$data);
      return $data;
    }
  }

  return '';
}

function phs_write($sess_id,$data) {
  // we only write to a file. 
  // It's the async daemon that will push it to memcache
  global $phs_path;
  file_put_contents("$phs_path/php_sess_$sess_id",$data);
  // needs no return code according to
  // http://de2.php.net/manual/en/function.session-set-save-handler.php
}

function phs_gc($lifetime) {
  // dummy function
}

function phs_close() {
  // don't do anything
}

function phs_destroy($sess_id) {
  // first destroy on the disk
  if(!unlink("$phs_path/php_sess_$sess_id")) { return FALSE; }

  // here we force a session destruction on memcache too
  global $phs_memc_host;
  global $phs_memc_port;
  $memc = new Memcache;
  $memc->connect($phs_memc_host,$phs_memc_port);
  $memc->delete("php_sess_$sess_id");

  return TRUE;
}