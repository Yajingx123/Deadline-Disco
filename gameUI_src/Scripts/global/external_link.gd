extends RefCounted
class_name ExternalLink

# Web：整页跳转；桌面：系统浏览器打开。登录由主站 home.html 完成，Godot 内不再校验 session。

static func open(url: String) -> void:
	if url.is_empty():
		return
	var safe := url.replace("\\", "\\\\").replace("'", "\\'")
	if OS.has_feature("web"):
		JavaScriptBridge.eval("window.location.href = '" + safe + "';")
	else:
		OS.shell_open(url)


# Web: open external page in a new tab so Godot stays alive.
static func open_in_new_tab(url: String) -> void:
	if url.is_empty():
		return
	if OS.has_feature("web"):
		var safe := url.replace("\\", "\\\\").replace("'", "\\'")
		JavaScriptBridge.eval("window.open('" + safe + "', '_blank', 'noopener');")
	else:
		OS.shell_open(url)


# 从 Godot 大厅切回经典主页（与 home.html 上 Switch 进入 Godot 互为反向；主站 Cookie 写入在 home 侧完成）
static func open_classic_home() -> void:
	if not OS.has_feature("web"):
		OS.shell_open("http://127.0.0.1:8001/home.html?exit_godot=1")
		return
	var port := str(JavaScriptBridge.eval("window.location.port", true))
	var host := str(JavaScriptBridge.eval("window.location.hostname", true))
	var url: String
	if port == "5500":
		url = "http://" + host + ":8001/home.html?exit_godot=1"
	else:
		var origin := str(JavaScriptBridge.eval("window.location.origin", true))
		url = origin + "/home.html?exit_godot=1"
	open(url)
