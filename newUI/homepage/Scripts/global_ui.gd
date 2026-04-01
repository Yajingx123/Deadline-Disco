extends CanvasLayer

# 悬停颜色
var normal_color : Color = Color(1,1,1)
var hover_color : Color = Color(0.8, 0.8, 0.8)

@onready var login_popup = $LoginPopup
@onready var lock_bg = $LoginPopup/LockBg  # 引用遮罩


# ==========================
# 登录按钮 → 打开弹窗
# ==========================
func _on_login_mouse_entered() -> void:
	$Login.modulate = hover_color

func _on_login_mouse_exited() -> void:
	$Login.modulate = normal_color

func _on_login_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton && event.pressed && event.button_index == MOUSE_BUTTON_LEFT:
		print("打开登录窗口")
		login_popup.visible = true
		lock_bg.visible = true  # 开启锁定


# ==========================
# 切换按钮
# ==========================
func _on_switch_mouse_entered() -> void:
	$Switch.modulate = hover_color

func _on_switch_mouse_exited() -> void:
	$Switch.modulate = normal_color

func _on_switch_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton:
		if event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
			print("切换按钮点击，返回旧版主页")
			var target_url := "http://127.0.0.1:8001/home.html"
			if OS.has_feature("web"):
				JavaScriptBridge.eval("window.location.href = '" + target_url + "';")
			else:
				OS.shell_open(target_url)


# ==========================
# 菜单按钮
# ==========================
func _on_menu_mouse_entered() -> void:
	$Menu.modulate = hover_color

func _on_menu_mouse_exited() -> void:
	$Menu.modulate = normal_color

func _on_menu_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton:
		if event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
			print("菜单按钮点击！")


# ==========================
# 关闭弹窗
# ==========================
func _on_submit_btn_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton && event.pressed && event.button_index == MOUSE_BUTTON_LEFT:
		print("关闭登录窗口")
		login_popup.visible = false
		lock_bg.visible = false  # 解除锁定
