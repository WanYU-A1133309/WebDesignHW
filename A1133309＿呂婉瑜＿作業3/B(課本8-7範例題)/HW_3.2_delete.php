<?php
    $id = $_GET["id"];
    if (isset($id)) {
        setcookie("Cart[" . $id . "]", "", time() - 3600);
    }
    header("Refresh:0;url=HW_3.2_shoppingcart.php");
    exit();
?>