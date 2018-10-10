<html>
<body>
<?php
require_once __DIR__ . '/loader.php';
if(!empty($_COOKIE['user_id'])) {
    echo 'Welcome back, ' . $_COOKIE['user_id'];
} else {
    echo 'Welcome, anonymous.';
}
?>

<?php if(empty($_COOKIE['user_id'])) { ?>
    <h1>Sign up</h1>
    <form action="signup.php" method="get">
        <input type="email" name="email" placeholder="email"/>
        <input type="password" name="password" placeholder="password"/>
        <input type="submit"/>
    </form>

    <h1>Sign in</h1>
    <form action="login.php" method="get">
        <input type="email" name="email" placeholder="email"/>
        <input type="password" name="password" placeholder="password"/>
        <input type="submit"/>
    </form>

<?php } ?>

<?php if(!empty($_COOKIE['user_id'])) { ?>

    <form action="createpost.php" method="get">
        <textarea name="body"></textarea>
        <input type="submit"/>
    </form>

<?php }
$SQL = new \Kyle\SQL();
$posts = $SQL->execute('SELECT body, user_id FROM kyle_posts')->getResult();
echo "<h1>Posts:</h1>";
echo "<table border='1'><thead><th>body</th><th>user_id</th></thead>";
foreach($posts as $post) {
    echo '<tr>';
    echo '<td>' . $post['body'] . "</td>";
    echo '<td>' . $post['user_id'] . "</td>";
    echo '</tr>';
}
echo "</table>";
?>
</body>
</html>