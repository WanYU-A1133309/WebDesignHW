<?php
session_start();

if(isset($_SESSION['login'])){
    if($_SESSION['login']=='teacher'){
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

$studentStatus = (isset($_SESSION['isSigned']) && $_SESSION['isSigned'] == true) ? "學生已簽到" : "學生未簽到";
?>

<html>
    <head>
        <title>教師管理頁面</title>
    </head>

    <body> 
        <center>
            <h1>教師管理面板</h1>
            <hr width="400">

            <h3>本週學生簽到清單</h3>
            <table border="1" cellpadding="10" cellspacing="0" bgcolor="white">
                <tr bgcolor="snow">
                    <th>學號</th>
                    <th>姓名</th>
                    <th>簽到狀態</th>
                </tr>
                <tr>
                    <td>a</td>
                    <td>學生 A</td>
                    <td><b><?php echo $studentStatus; ?></b></td>
                </tr>
            </table>

            <br>
            <hr width="400">
            <a href="HW_3_logout.php">登出系統</a>
        </center>

    </body>
</html>
