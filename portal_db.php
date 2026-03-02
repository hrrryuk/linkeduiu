<?php

$db_server = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "portal_db";

$portal_error_message = "COULD NOT CONNECT TO THE DATABASE";
$portal_success_message = "DATABASE CONNECTION SUCCESSFUL";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $portal = new mysqli($db_server, $db_user, $db_pass, $db_name);
    //echo $conn_success_message;
} catch (mysqli_sql_exception $mse) {
    exit($portal_error_message);
}
return $portal;
