const AUTH_API_URL = "/Auth/backend/api";

export async function submitAuth(mode, payload) {
    const endpoint = mode === "login" ? "/login.php" : "/register.php";
    try {
        const response = await fetch(`${AUTH_API_URL}${endpoint}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "include",
            body: JSON.stringify(payload) 
        });
        return await response.json();
    } catch (error) {
        console.error("Auth Request Failed:", error);
        return { status: "error", message: "网络请求失败，请检查 Auth 后端是否可访问" };
    }
}
