<html>
    <head>
        <title>Summer Camp Registration Form</title>
    </head>
        
    
    <body>
        
        <center>
            <h1>
                <font color="DeepPink">2026 科學夏令營報名表</font>       
            </h1>
            
            <p>請填寫以下表格完成報名，如有任何問題請洽：0X-XXXX-XXXX。</p>
        </center>
        
        <form action="" method="">
            <table width="800" border="1" bgcolor="WhiteSmoke" align="center" cellpadding="8" cellspacing="0">
                <tr>
                   <td width="15%" align="center" bgcolor="HotPink">
                       <font color="white">姓名</font>
                    </td> 
                
                    <td width="19%">
                        <input type="text" placeholder="請輸入姓名" name="nName" size="20" required>
                    </td> 
                    <td width="15%" align="center" bgcolor="HotPink">
                        <font color="white">性別</font>
                    </td> 
                    <td width="19%">
                        男<input type="radio" name="gender" value="m">
                        女<input type="radio" name="gender" value="f">
                    </td> 
                
                    <td width="14%" align="center" bgcolor="HotPink">
                        <font color="white">飲食需求<font>
                    </td> 
                    <td width="18%">
                        葷<input type="radio" name="diet" value="meat" required>
                        素<input type="radio" name="diet" value="vag" required>
                    </td> 
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">身分證字號</font>
                    </td> 
                    <td>
                        <input type="text" placeholder="請輸入身分證字號" name="nId" size="20" required>
                    </td> 

                    <td align="center" bgcolor="HotPink">
                        <font color="white">就讀學校</font>
                    </td>
                    <td colspan="3">
                        <input type="text" placeholder="請輸入就讀學校" name="nSchool" size="20" required>
                    </td> 
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">出生日期</font>
                    </td> 
                    <td>
                        <input type="date" name="nDate" value="date">
                    </td> 

                    <td align="center" bgcolor="HotPink">
                        <font color="white">年級班別</font>
                    </td>
                    <td colspan="3">
                        年級：
                        <select name"nGrade" >
                        <option value="" selected>國一</option>
                        <option value="">國二</option>
                        <option value="">國三</option>
                        <option value="">高一</option>
                        <option value="">高二</option>
                        <option value="">高三</option>
                        </select>
                        &nbsp
                        &nbsp
                        &nbsp
                        班別：<input type="text" placeholder="請輸入班別" name="nClass" size="20" required>
                    </td> 
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">緊急聯絡人</font>
                    </td> 
                    <td>
                        <input type="text" placeholder="請輸入緊急連絡人姓名" name="nContactPerson" size="20" required>
                    </td> 

                    <td align="center" bgcolor="HotPink">
                        <font color="white">連絡電話</font>
                    </td>
                    <td colspan="3">
                        <input type="text" placeholder="請輸入連絡電話" name="nPhoneNumber" size="20" required>
                    </td> 
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">住家地址</font>
                    </td> 
                    <td colspan="5">
                        <input type="text" placeholder="請輸入住家地址" name="nAddress" size="97" required>
                    </td> 
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">Email</font>
                    </td> 
                    <td>
                        <input type="email" name="nEmail" placeholder="請輸入Email" required>
                    </td>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">衣服Size</font>
                    </td> 
                    <td colspan="3">
                        S<input type="radio" name="cSize" value="sSize" required>
                        M<input type="radio" name="cSize" value="mSize" required>
                        L<input type="radio" name="cSize" value="lSize" required>
                        XL<input type="radio" name="cSize" value="xlSize" required>
                    </td>
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">報名梯次</font>
                    </td> 
                    <td colspan="5">
                        <input type="radio" name="mTired" value="first" required>第一梯次&nbsp&nbsp115年7月7日-7月11日&nbsp&nbsp&nbsp&nbsp
                        <input type="radio" name="mTired" value="Second" required>第二梯次&nbsp&nbsp115年8月18日-8月22日
                    </td> 
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">交通方式</font>
                    </td> 
                    <td colspan="5">
                        <input type="radio" name="transportation" value="first" required>自行前往(高雄大學)&nbsp&nbsp&nbsp&nbsp
                        <input type="radio" name="transportation" value="first" required>高雄火車站前集合(AM&nbsp&nbsp8:30)&nbsp&nbsp&nbsp&nbsp
                        <input type="radio" name="transportation" value="Second" required>高雄高鐵站前集合(AM&nbsp&nbsp8:00)
                    </td>
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">得知此活動方式</font>
                    </td> 
                    <td colspan="5">
                        <input type="checkbox" name="way" value="fb">FB&nbsp&nbsp
                        <input type="checkbox" name="way" value="ig">IG&nbsp&nbsp&nbsp&nbsp
                        <input type="checkbox" name="way" value="x">X&nbsp&nbsp&nbsp&nbsp
                        <input type="checkbox" name="way" value="paper">報章雜誌&nbsp&nbsp&nbsp&nbsp
                        <input type="checkbox" name="way" value="TV">電視廣告&nbsp&nbsp&nbsp&nbsp
                        <input type="checkbox" name="way" value="recommend">他人推薦&nbsp&nbsp&nbsp&nbsp
                        <input type="checkbox" name="way" value="other">其他&nbsp&nbsp&nbsp&nbsp
                    </td>
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">想說的話</font>
                    </td>
                    <td colspan="5">
                        <textarea name="comment" rows="5" cols="97" style="color: Gray;">留下你想對我們說的話吧！</textarea>
                    </td>
                </tr>
                <tr>
                    <td align="center" bgcolor="HotPink">
                        <font color="white">注意事項</font>
                    </td> 
                    <td colspan="5">
                        <input type="checkbox" name="way" value="fb" required>本人確認報名「2026 科學夏令營」營隊，且已詳閱並同意報名注意事項中的退費辦法與相關權益說明
                        <br>
                        <font color="HotPink">
                            1. 報名後如因故無法參加，請於活動前 14 天通知，可全額退費（扣除匯款手續費）。<br>
                            2. 活動前 7-13 天取消者退費 70%；活動前 3 天內取消恕不退費。<br>
                            3. 如遇天災或不可抗力因素導致營隊取消，將由本會統一通知並全額退款。<br>
                            4. 本營隊投保公共意外責任險，請務必填寫正確身分證字號與生日以利投保。<br>
                        </font>              
                    </td>
                </tr>
                <tr>
                    <td colspan="6" align="center" bgcolor="WhiteSmoke">
                        <input type="submit" value="確認送出報名表">
                        <input type="reset" value="重新填寫">
                    </td>  
                </tr>     
            </table>
        </form>
    </body>
</html>
