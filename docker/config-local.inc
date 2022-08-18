<?php
$sql_host = getenv('DB_HOST');
$sql_connectionType='host';
$sql_databaseEngine = 'pgsql';
$sql_databasePort = 5432;
$sql_username = getenv('POSTGRES_USER');
$sql_password = getenv('POSTGRES_PASSWORD');
$sql_ro_username = getenv('RO_USERNAME');
$sql_ro_password = getenv('RO_PASSWORD');
$bounce_host = getenv('BOUNCE_HOST');
$cookie_host = getenv('COOKIE_HOST');
?>
