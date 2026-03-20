const AUTH_API_URL = "http://127.0.0.1:8001/api";

export async function submitAuth(mode, payload) {
    const endpoint = mode === "login" ? "/login.php" : "/register.php";
    try {
        const response = await fetch(`${AUTH_API_URL}${endpoint}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            // 直接将包含 username, email, password 或 identifier 的 payload 发给后端
            body: JSON.stringify(payload) 
        });
        return await response.json();
    } catch (error) {
        console.error("Auth Request Failed:", error);
        return { status: "error", message: "网络请求失败，请检查 Auth 后端是否运行在 8001 端口" };
    }
}