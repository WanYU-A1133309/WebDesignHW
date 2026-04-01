<?php
$nName = $_POST["nName"];
$nGender=$_POST["nGender"];
$nDiet=$_POST["nDiet"];
$nId=$_POST["nId"];
$nSchool=$_POST["nSchool"];
$nDate = $_POST["nDate"];
$nGrade=$_POST["nGrade"];
$nClass=$_POST["nClass"];
$nContactPerson=$_POST["nContactPerson"];
$nPhoneNumber=$_POST["nPhoneNumber"];
$nAddress=$_POST["nAddress"];
$nEmail=$_POST["nEmail"];
$nSize=$_POST["nSize"];
$nTiered=$_POST["nTiered"];
$nTransportation=$_POST["nTransportation"];
$nWay = $_POST["nWay"];
$nComment = $_POST["comment"];

echo "報名輸入結果：<br/>";

echo "用戶姓名：".$nName."<br/>";

if($nGender=="m"){
    echo "用戶性別：男性<br/>";
}else{
    echo "用戶性別：女性<br/>";
}

if($nDiet=="meat"){
    echo "用戶飲食需求：葷<br/>";
}else{
    echo "用戶飲食需求：素<br/>";
}

echo "用戶身份證字號：".$nId."<br/>";

echo "用戶生日：".$nDate."<br/>";

echo "用戶就讀學校：".$nSchool."<br/>";

echo "用戶年級/班別：".$nGrade."/".$nClass."<br/>";

echo "用戶緊急連絡人：".$nContactPerson."<br/>";

echo "用戶連絡電話：".$nPhoneNumber."<br/>";

echo "用戶住家地址：".$nAddress."<br/>";

echo "用戶電子郵件：".$nEmail."<br/>";

echo "用戶衣服尺寸：";
switch($nSize){
    case "sSize";
        echo "S<br/>";
        break;
    case "mSize";
        echo "M<br/>";
        break;
    case "lSize";
        echo "L<br/>";
        break;
    case "xlSize";
        echo "XL<br/>";
        break;
}

if($nTiered=="first"){
    echo "用戶報名梯次：第一梯次<br/>";
}else{
    echo "用戶報名梯次：第二梯次<br/>";
}

if($nTiered=="self"){
    echo "用戶交通方式：自行前往<br/>";
}elseif($nTiered=="kTrain"){
    echo "用戶交通方式：高雄火車站前集合<br/>";
}else{
    echo "用戶交通方式：高雄高鐵站前集合<br/>";
}

echo "用戶得知本活動管道：";
foreach($nWay as $nW){
    switch($nW){
        case "fb":
            echo "FB&nbsp";
            break;
        case "ig":
            echo "IG&nbsp";
            break;
        case "x":
            echo "X&nbsp";
            break;
        case "paper":
            echo "報章雜誌&nbsp";
            break;
        case "TV":
            echo "電視廣告&nbsp";
            break;
        case "recommend":
            echo "他人推薦&nbsp";
            break;
        case "other":
            echo "其他&nbsp";
            break;
    }
}
echo "<br/>";

echo "用戶意見回饋：<br/>";
echo nl2br($nComment);

?>