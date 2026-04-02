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
