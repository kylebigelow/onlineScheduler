<?php
require_once __DIR__ . '/loader.php';
// use $_GET for get and $_POST for post
if(!empty($_GET) && !empty($_GET['email']) && !empty($_GET['password'])) {
    $SQL = new \Kyle\SQL();
    $email = $_GET['email'];
    $password = $_GET['password'];
    $storeUser = $SQL->execute('INSERT INTO kyle_users (email, `password`) VALUES ("' . $email . '", "' . $password . '")');
    echo 'Your user ID: ' . $storeUser->getInsertId();
}
header('Location: ' . HOMEPAGE);