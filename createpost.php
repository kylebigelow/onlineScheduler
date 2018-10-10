<?php
require_once __DIR__ . '/loader.php';
// use $_GET for get and $_POST for post
if(!empty($_GET) && !empty($_GET['body'])) {
    $userID = empty($_COOKIE['user_id'])
        ? null
        : $_COOKIE['user_id'];
    if(is_null($userID))
        die("You are not logged in!");
    $body = $_GET['body'];
    $SQL = new \Kyle\SQL();
    $SQL->execute('INSERT INTO kyle_posts (body, user_id) VALUES ("' . $body . '", "' . $userID . '")');
}
header('Location: ' . HOMEPAGE);