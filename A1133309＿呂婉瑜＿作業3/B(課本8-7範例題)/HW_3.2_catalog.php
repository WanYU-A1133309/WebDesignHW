<?php
    session_start();
    $_SESSION["cartUser"] = "User";
?>
<html>
    <head>
        <title>商品選購頁</title>
    </head>

    <body bgcolor="Snow">
        <form action="HW_3.2_savecart.php" method="post">
            <font color="Navy">選擇商品：</font>
            <select name="uProduct">
                <option value="S001,10吋平板電腦,12000">10吋平板電腦 - $12000</option>
                <option value="S002,16.5吋筆記型電腦,27000">16.5吋筆記型電腦 - 27000</option>
                <option value="S003,iPhone智慧型手機,21000">iPhone智慧型手機 - $21000</option>
            </select>
            <input type="number" placeholder="請輸入欲購買數量" name="pQuantity" required>
            <input type="submit" value="訂購">
        </form>

        <hr width="500" align="left">

        | <a href="hw_3.2_catalog.php">商品目錄</a> | <a href="HW_3.2_shoppingcart.php">檢視購物車</a> |  
    </body>    
</html>
