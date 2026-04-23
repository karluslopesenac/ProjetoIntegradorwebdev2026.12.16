<?php
$server = 'localhost';
$user = 'root';
$pass = '';
$db = 'umbrella';
$conn = mysqli_connect($server, $user, $pass, $db);
if (!$conn) die('Erro conexão');
?>
