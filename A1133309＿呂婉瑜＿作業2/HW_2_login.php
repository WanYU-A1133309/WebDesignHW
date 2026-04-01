<?php
$fID="aaa";
$fPWD="123456";
$msg=" ";

if(isset($_POST["uID"])&&isset($_POST["uPWD"])){
    $uID=$_POST["uID"];
    $uPWD=$_POST["uPWD"];

    if($fID==$uID&&$fPWD==$uPWD){
        header("Location: HW_2.php");
    }else{
        $msg="登入失敗，請重新輸入！";
    }

}
?>

<html>
    <head>
        <title>login</title>
    </head>
        
    
    <body>
        
        <center>
            <h1>
                <font color="DeepPink">2026 科學夏令營報名表</font>       
            </h1>
            
            <p>請輸入帳號密碼登入。</p>
        </center>
        <form action="HW_2_login.php" method="post">
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
    </body>
</html>