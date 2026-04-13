<?php
$msg = isset($_GET['msg']) ? $_GET['msg'] : "";
?>


<html>
    <head>
        <title>login</title>
    </head>
        
    
    <body>
        
        <center>
            <h1>簽到系統登入</h1>

            <hr width="400">

            <p>請輸入帳號密碼並選擇角色。</p>
        </center>

        <form action="HW_3_logincheck.php" method="post">
            <table width="350" border="0" bgcolor="WhiteSmoke" align="center" cellpadding="10" cellspacing="0">
                <tr>
                    <td align="center">
                        帳號(ID):&nbsp<input type="text" name="uID">
                    </td>
                </tr>
                <tr>
                    <td align="center">
                        密碼(PWD):&nbsp<input type="password" name="uPWD">
                    </td>
                </tr>
                <tr>
                    <td align="center">
                        身分:&nbsp
                        <select name="uRole">
                            <option value="學生">學生</option>
                            <option value="教師">教師</option>
                            <option value="管理者">管理者</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td align="center">
                        <input type="submit" value="登入">
                    </td>
                </tr>
                <tr>
                    <td align="center">
                        <?php
                        if($msg != " " ){
                            echo "<font color='red'><b>$msg</b></font>";
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </form>
        <center>
            <?php
                if(isset($_COOKIE['uID'])){
                    echo $_COOKIE['uID']."歡迎回來！";
                    echo "<a href='HW_3_cookiedel.php'>刪除COOKIE</a>";
                }            
            ?>
        </center>
    </body>
</html>