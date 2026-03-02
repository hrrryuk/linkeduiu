<?php
session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit;
//<a href="logout.php" onclick="return confirm('Are you sure you want to logout?')">Logout</a>