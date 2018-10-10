<?php
require_once __DIR__ . '/loader.php';
// use $_GET for get and $_POST for post
if(!empty($_GET) && !empty($_GET['email']) && !empty($_GET['password'])) {
    $SQL = new \Kyle\SQL();
    $email = $_GET['email'];
    $password = $_GET['password'];
    $user = $SQL->execute('SELECT `id` FROM kyle_users WHERE email = "' . $email . '" AND `password` = "' . $password . '"', [], true)->getResult();
    if(empty($user))
        die('Failed login!');
    if(!empty($user)) {
        $userID = $user['id'];
        setcookie('user_id', $userID, time() + (3600 * 24 * 7), '', '', '', true);
    }
}
header('Location: ' . HOMEPAGE);