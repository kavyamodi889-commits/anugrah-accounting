<?php
session_start();
session_destroy();
header("Location: anugrah_home.php");
exit();
?>