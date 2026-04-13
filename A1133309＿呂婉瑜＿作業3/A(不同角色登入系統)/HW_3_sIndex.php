<?php
session_start();

if(isset($_SESSION['login'])){
    if($_SESSION['login']=='student'){
        $welcomeMsg = "登入成功";
    }else{
        echo "<h1>非法進入網頁！兩秒後回登入頁</h1>";
        header("Refresh:3;url=HW_3_login.php");
        exit();
    }
}else{
    echo "<h1>非法進入網頁！兩秒後回登入頁</h1>";
    header("Refresh:3;url=HW_3_login.php");
    exit();
}

$signMsg = "尚未簽到";
if (isset($_POST['sign'])) {
    $_SESSION['isSigned'] = true;
    $signMsg = "簽到成功！";
}
if (isset($_SESSION['isSigned']) && $_SESSION['isSigned'] == true) {
    $signMsg = "已簽到！";
}
?>

<html>
    <head>
        <title>學生簽到頁</title>
    </head>

    <body>
        <center>
            <h1>學生簽到系統</h1>
            
            <hr width="300">

            <h3>每日簽到</h3>
            <p>狀態：<?php echo $signMsg; ?></p>

            <form method="post">
                <input type="submit" name="sign" value="按此簽到">
            </form>

            <hr width="300">
            <br>
            <a href="HW_3_logout.php">登出系統</a>
        </center>



    </body>
</html>
