// 阻擋不合格的表單送出
document.addEventListener("DOMContentLoaded", function () {
    // 監聽註冊表單的送出事件
    const registerForm = document.getElementById("register-form");
    
    if (registerForm) {
        registerForm.addEventListener("submit", function (event) {
            // 抓取密碼與確認密碼輸入框的值
            const password = document.getElementById("reg-password").value;
            const confirmPassword = document.getElementById("reg-confirm").value;
            
            // 找出用來顯示錯誤的區塊，如果原本沒有就動態生一個
            let errorBox = document.querySelector(".error-msg");

            if (password !== confirmPassword) {
                // 不准網頁重新整理與送出給 PHP
                event.preventDefault();

                if (!errorBox) {
                    errorBox = document.createElement("div");
                    errorBox.className = "error-msg";
                    // 將錯誤方塊塞到表單的最上方
                    registerForm.parentNode.insertBefore(errorBox, registerForm);
                }

                // 塞入文字，並貼心平滑捲動到最上方讓使用者發現
                errorBox.innerText = "兩次輸入的密碼不一致，請重新檢查！";
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }
});

// 密碼眼睛
function togglePasswordVisibility(inputId, toggleElement) {
    // 1. 抓到對應的密碼輸入框
    const passwordInput = document.getElementById(inputId);
    
    if (!passwordInput) return;

    // 2. 切換 input 的 type 屬性
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text'; // 變成看得到明文
        toggleElement.classList.add('slash'); // 加上 CSS 劃掉的斜線，代表「可看見」狀態
    } else {
        passwordInput.type = 'password'; // 變回黑點隱藏
        toggleElement.classList.remove('slash'); // 拿掉斜線
    }
}