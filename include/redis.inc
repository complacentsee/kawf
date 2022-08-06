<?php

class RedisAuth {
  public function __construct($host, $port, $pass) {
    $this->hostname=$host;
    $this->port=$port;
    $this->password=$pass;
  }
}

$redis = null;

// DO NOT USE THIS FUNCTION DIRECTLY
//
// Use redis_connect() below to connect to the database.
function _redis_connect($redis_auth) {
  global $redis;
  try {
    $host = $redis_auth->hostname;
    $port = $redis_auth->port;
    $pass = $redis_auth->password;
    $redis = new Redis();
    $redis->pconnect($host, $port, 1, NULL, 0, 0, ['auth' => [$pass]]);
    $redis->setOption(Redis::OPT_BACKOFF_ALGORITHM, Redis::BACKOFF_ALGORITHM_DECORRELATED_JITTER);
    $redis->setOption(Redis::OPT_BACKOFF_BASE, 500); // base for backoff computation: 500ms
    $redis->setOption(Redis::OPT_BACKOFF_CAP, 750); // backoff time capped at 750ms
  } catch(RedisException $e) {
      var_dump($redis_auth);
      echo $e;
     exit;
  }
}

// Connect to the database specified by $redis_auth or the globals:
// $redis_host, $redis_port, $redis_password
// The database handle is the global $redis.
function redis_connect($reddis_auth=null) {
  global $redis;

  if (!isset($reddis_auth)) {
    global $redis_host, $redis_port, $redis_password;
    $reddis_auth = new RedisAuth('localhost', $redis_port, $redis_password);
    if(isset($redis_host)) {
      $reddis_auth->hostname = $redis_host;
    }
  }

  if(!$redis) {
    _redis_connect($reddis_auth);
  }
}

?>
