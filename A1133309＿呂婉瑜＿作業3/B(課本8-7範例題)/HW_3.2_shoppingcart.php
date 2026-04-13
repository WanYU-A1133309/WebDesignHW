<?php
    session_start();
?>
<html>
    <head>
        <title>購物車內容頁</title>
    </head>

    <body bgcolor="Snow">
        <table border="0" cellpadding="10">
            <tr bgcolor="DarkTurquoise">
                <th>功能</th>
                <th>編號</th>
                <th>名稱</th>
                <th>價格</th>
                <th>數量</th>
            </tr>
            <?php
                $total=0;

                if(isset($_COOKIE["Cart"])){
                    foreach($_COOKIE["Cart"] as $id => $value){
                        $item=explode(",", $value);
                        $price=$item[2];
                        $quantity=$item[3];
                        $total+=$price*$quantity;

                        echo "<tr bgcolor='Pink'>";
                        echo "<td><a href='HW_3.2_delete.php?id=$id'>刪除</a></td>";
                        echo "<td>".$item[0]."</td>";
                        echo "<td>".$item[1]."</td>";
                        echo "<td>".$item[2]."</td>";
                        echo "<td>".$item[3]."</td>";
                        echo "</tr>";
                    }
                }
            ?>
            <tr bgcolor="Khaki">
                <td colspan="5" align="right">
                    總金額 = NT$<?php echo $total;?>元
                </td>
            </tr>
        </table>    
        <hr width="400" align="left">

        | <a href="hw_3.2_catalog.php">商品目錄</a> | <a href="HW_3.2_shoppingcart.php">檢視購物車</a> |  
    </body>    
</html>