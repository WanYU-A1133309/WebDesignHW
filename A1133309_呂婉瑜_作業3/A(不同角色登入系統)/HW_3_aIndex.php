<?php
session_start();

if(isset($_SESSION['login'])){
    if($_SESSION['login']=='adminer'){
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

if(isset($_POST['reset_sign'])){
    unset($_SESSION['isSigned']); //刪除學生簽到記錄
    $adminMsg = "已重設學生簽到狀態！";
}

if(isset($_POST['del_cookie'])){
    setcookie("uID", "", time()-100); //刪除Cookie，不需要額外再寫一個cookiedel.php
    $adminMsg = "已成功刪除登入Cookie！";
}
?>

<html>
    <head>
        <title>管理者頁面</title>
    </head>
    <body> 
        <center>
            <h1>系統管理後台</h1>
            <hr width="500">

            <?php 
            if(isset($adminMsg)) echo "<p style='color:blue;'>$adminMsg</p>"; 
            ?>

            <h3>管理功能控制台</h3>
            
            <table border="1" cellpadding="15" cellspacing="0" bgcolor="snow">
                <tr>
                    <td valign="top" align="center">
                        <strong>簽到管理</strong><br><br>
                        <form method="post">
                            <input type="submit" name="reset_sign" value="清空學生簽到紀錄">
                        </form>
                    </td>
                    <td valign="top" align="center">
                        <strong>Cookie管理</strong><br><br>
                        <form method="post">
                            <input type="submit" name="del_cookie" value="刪除登入 Cookie" style="color:red; font-weight:bold;">
                        </form>
                    </td>
                </tr>
            </table>

            <br><br>
            <hr width="500">
            <a href="HW_3_logout.php">登出</a> 
        </center>
    </body>
</html>