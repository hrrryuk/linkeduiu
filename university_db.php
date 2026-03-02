<?php

$db_server = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "university_db";

$conn_error_message = "COULD NOT CONNECT TO THE DATABASE";
$conn_success_message = "DATABASE CONNECTION SUCCESSFUL";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $university = new mysqli($db_server, $db_user, $db_pass, $db_name);
    //echo $conn_success_message;
} catch (mysqli_sql_exception $mse) {
    exit($conn_error_message);
}
