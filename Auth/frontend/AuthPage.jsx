import React, { useState } from "react";
import { submitAuth } from "./authApi";

export default function AuthPage({ onClose, onSuccess }) {
  const [isLoginMode, setIsLoginMode] = useState(true);
  
  const [identifier, setIdentifier] = useState(""); 
  const [username, setUsername] = useState("");     
  const [email, setEmail] = useState("");           
  const [password, setPassword] = useState("");
  // 【新增】：确认密码状态
  const [confirmPassword, setConfirmPassword] = useState(""); 
  const [loading, setLoading] = useState(false);
  const [feedback, setFeedback] = useState("");
  const [feedbackType, setFeedbackType] = useState("info");

  const handleSubmit = async (e) => {
    e.preventDefault();

    // 【新增】：注册模式下校验两次密码是否一致
    if (!isLoginMode && password !== confirmPassword) {
      setFeedbackType("error");
      setFeedback("Passwords do not match. Please try again.");
      return;
    }

    setFeedback("");
    setLoading(true);
    let payload = {};
    let mode = isLoginMode ? "login" : "register";

    if (isLoginMode) {
      payload = { identifier, password };
    } else {
      payload = { username, email, password };
    }

    const data = await submitAuth(mode, payload);
    setLoading(false);
    
    if (data.status === "success") {
      setFeedbackType("success");
      setFeedback(`${isLoginMode ? "Login" : "Registration"} successful.`);
      onSuccess(data.username);
    } else {
      setFeedbackType("error");
      setFeedback(`Failed: ${data.message}`);
    }
  };

  // 标签统一样式，颜色使用全局变量 var(--secondary)
  const labelStyle = {
    fontSize: "13px",
    fontWeight: "700",
    color: "var(--secondary)", 
    marginTop: "1.2rem",
    marginBottom: "6px",
    display: "block"
  };

  return (
    <div style={{
      position: "fixed", top: 0, left: 0, width: "100vw", height: "100vh",
      // 【修改】：使用 globals.css 中的背景色变量
      backgroundColor: "var(--bg)", 
      zIndex: 9999, 
      display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center",
      // 移除内联 fontFamily，让其自动继承 body 的全局字体设置
    }}>
      
      {/* 顶部中央提示文字 */}
      <div style={{ marginBottom: "1.5rem", fontSize: "14px", color: "var(--muted)" }}>
        Welcome to <strong style={{ color: "var(--secondary)", fontSize: "1.2rem", textTransform: "uppercase" }}>Acad<span style={{ color: "var(--primary)" }}>Beat</span></strong>
      </div>

      <div style={{
        // 【修改】：使用 globals.css 中的卡片背景、边框和阴影
        background: "var(--panel)", 
        padding: "3rem 3.5rem", 
        borderRadius: "16px", 
        boxShadow: "var(--shadow)", 
        width: "420px",
        position: "relative",
        border: "1px solid var(--line)" 
      }}>
        
        {/* 右上角的关闭按钮 */}
        <button 
          onClick={onClose}
          style={{
            position: "absolute", top: "20px", right: "25px",
            background: "transparent", border: "none", fontSize: "28px",
            color: "var(--muted)", cursor: "pointer", padding: "0",
            lineHeight: "1", boxShadow: "none"
          }}
          title="Back to Home"
        >
          &times;
        </button>

        {/* 顶部切换 Tab */}
        <div style={{ display: "flex", marginBottom: "2.5rem", position: "relative" }}>
          <button 
            type="button"
            onClick={() => setIsLoginMode(true)}
            style={{ 
              flex: 1, padding: "12px", background: "transparent", border: "none", 
              color: isLoginMode ? "var(--primary)" : "var(--muted)", 
              boxShadow: "none", borderRadius: 0,
              cursor: "pointer", fontWeight: "700", fontSize: "16px", transition: "color 0.3s" 
            }}
          >
            Login
          </button>
          <button 
            type="button"
            onClick={() => setIsLoginMode(false)}
            style={{ 
              flex: 1, padding: "12px", background: "transparent", border: "none", 
              color: !isLoginMode ? "var(--primary)" : "var(--muted)", 
              boxShadow: "none", borderRadius: 0,
              cursor: "pointer", fontWeight: "700", fontSize: "16px", transition: "color 0.3s" 
            }}
          >
            Register
          </button>
          {/* 指示线条使用全局主色调 */}
          <div style={{ 
            position: "absolute", bottom: "-2px", left: isLoginMode ? "0%" : "50%", 
            width: "50%", height: "2px", background: "var(--primary)", 
            transition: "left 0.3s ease" 
          }} />
        </div>

        <form onSubmit={handleSubmit} style={{ display: "flex", flexDirection: "column" }}>
          
          {/* 注意：这里的 input 没有写满内联样式。
            因为 globals.css 已经定义了 input { border-radius: 8px; background: #f5f1eb; ... }
            它们会自动继承这些漂亮的全局样式！
          */}
          {isLoginMode ? (
            <>
              <div>
                <label style={labelStyle}>Username or Email</label>
                <input type="text" value={identifier} onChange={(e) => setIdentifier(e.target.value)} required style={{ width: "100%" }} placeholder="Enter username or email" />
              </div>
            </>
          ) : (
            <>
              <div style={{ marginTop: "-1.2rem" }}>
                <label style={labelStyle}>Username</label>
                <input type="text" value={username} onChange={(e) => setUsername(e.target.value)} required style={{ width: "100%" }} placeholder="Choose a username" />
              </div>
              <div>
                <label style={labelStyle}>Email Address</label>
                <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required style={{ width: "100%" }} placeholder="Enter your email" />
              </div>
            </>
          )}

          <div>
            <label style={labelStyle}>Password</label>
            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required style={{ width: "100%" }} placeholder="Enter your password" />
          </div>

          {/* 【新增】：注册模式下的确认密码框 */}
          {!isLoginMode && (
            <div>
              <label style={labelStyle}>Confirm Password</label>
              <input type="password" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} required style={{ width: "100%" }} placeholder="Re-enter your password" />
            </div>
          )}

          {!!feedback && (
            <div
              style={{
                marginTop: "1rem",
                padding: "12px 14px",
                borderRadius: "12px",
                background: feedbackType === "error" ? "rgba(217, 65, 91, 0.1)" : "rgba(26, 160, 109, 0.1)",
                color: feedbackType === "error" ? "#b33b55" : "#157a56",
                fontSize: "14px",
                fontWeight: "600",
                lineHeight: 1.5,
              }}
            >
              {feedback}
            </div>
          )}

          {/* 主按钮：直接使用原生 button 标签，不写死背景色，
            它会自动调用 globals.css 中的渐变背景和悬停动效
          */}
          <button 
            type="submit" 
            disabled={loading}
            style={{ 
              marginTop: "2.5rem", 
              width: "100%",
              padding: "12px", 
              fontSize: "16px"
            }}
          >
            {loading ? "Processing..." : (isLoginMode ? "Login" : "Register")}
          </button>
          
        </form>
      </div>
    </div>
  );
}
