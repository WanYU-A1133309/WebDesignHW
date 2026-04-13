<?php
session_start();
$sID="a";
$sPWD="1234";

$tID="teacher";
$tPWD="12345";

$aID="adminer";
$aPWD="123456";



if(isset($_POST["uID"])&&isset($_POST["uPWD"])){
    $uID=$_POST['uID'];
    $uPwd=$_POST['uPWD'];
    $uRole=$_POST['uRole'];

    $date=strtotime("+5 days", time());

    if($uID==$sID && $uPwd==$sPWD && $uRole=="學生"){
        $_SESSION['login']='student';
        setcookie("uID", $uID, $date);
        header("Refresh:0;url=HW_3_sIndex.php");
    }elseif($uID==$tID && $uPwd==$tPWD && $uRole=="教師"){
        $_SESSION['login']='teacher'; 
        setcookie("uID", $uID, $date); 
        header("Refresh:0;url=HW_3_tIndex.php");
    }elseif($uID==$aID && $uPwd==$aPWD && $uRole=="管理者"){
        $_SESSION['login']='adminer';   
        setcookie("uID", $uID, $date); 
        header("Refresh:0;url=HW_3_aIndex.php");
    }else{
        echo "登入失敗，請重新登入！";
        header("Refresh:2;url=HW_3_login.php");
    }
}
?>