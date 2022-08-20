<?php
$sql_host = getenv('DB_HOST'); // Host name or socket path
$sql_username = getenv('DB_USER');
$sql_password = getenv('DB_PASSWORD');
$sql_ro_username = getenv('RO_USERNAME');
$sql_ro_password = getenv('RO_PASSWORD');

//host, unix_socket
if(null !== getenv('DB_CONNECTION_TYPE'))
    $sql_connectionType = getenv('DB_CONNECTION_TYPE');

//'pgsql' or 'mysql'
if(null !== getenv('DB_ENGINE'))
    $sql_databaseEngine = getenv('DB_ENGINE');

//'5432' or '3306'
if(null !== getenv('DB_PORT'))
    $sql_databasePort = getenv('DB_PORT');

$bounce_host = getenv('BOUNCE_HOST');
$cookie_host = getenv('COOKIE_HOST');
?>
