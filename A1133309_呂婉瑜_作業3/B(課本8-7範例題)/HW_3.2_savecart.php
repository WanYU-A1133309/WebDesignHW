<?php
    session_start();
    
    if(isset($_POST["uProduct"])){
    $uProduct=$_POST['uProduct']; //格式："S001,10吋平板電腦,12000"
    $pQuantity=$_POST['pQuantity'];
    $dArr=explode("," , $uProduct); //將字串轉為陣列,[0]=編號、[1]=產品名、[2]=價格
    $id=$dArr[0]; //商品cookie名

    setcookie("Cart[".$id."]", $uProduct.",".$pQuantity, time()+3600);

    header("Refresh:0;url=HW_3.2_shoppingcart.php");
    exit(); 
}else{
    header("Refresh:0;url=HW_3.2_catalog.php");
    exit();
}
?>