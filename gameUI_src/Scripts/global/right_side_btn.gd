extends CanvasLayer

# 悬停颜色
var normal_color : Color = Color(1, 1, 1)
var hover_color : Color = Color(0.8, 0.8, 0.8)

@onready var login_popup = $LoginPopup
@onready var lock_bg = $LoginPopup/LockBg

# ==========================
# 切换按钮
# ==========================
func _on_switch_mouse_entered() -> void:
	if $Button/Switch:
		$Button/Switch.modulate = hover_color

func _on_switch_mouse_exited() -> void:
	if $Button/Switch:
		$Button/Switch.modulate = normal_color

func _on_switch_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton:
		if event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
			print("切换按钮点击！")

# ==========================
# 菜单按钮
# ==========================
func _on_menu_mouse_entered() -> void:
	if $Button/Menu:
		$Button/Menu.modulate = hover_color

func _on_menu_mouse_exited() -> void:
	if $Button/Menu:
		$Button/Menu.modulate = normal_color

func _on_menu_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton:
		if event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
			print("菜单按钮点击！返回主页")
			AutoTransition.change_scene("res://Scenes/homepage/homepage.tscn")

# ==========================
# 设置按钮
# ==========================
func _on_settings_mouse_entered() -> void:
	if $Button/Settings:
		$Button/Settings.modulate = hover_color

func _on_settings_mouse_exited() -> void:
	if $Button/Settings:
		$Button/Settings.modulate = normal_color

func _on_settings_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton and event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
		print("设置按钮点击！")

# ==========================
# 团队按钮
# ==========================
func _on_team_mouse_entered() -> void:
	if $Button/Team:
		$Button/Team.modulate = hover_color

func _on_team_mouse_exited() -> void:
	if $Button/Team:
		$Button/Team.modulate = normal_color

func _on_team_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton and event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
		print("团队按钮点击！")

# ==========================
# 个人资料按钮
# ==========================
func _on_profile_mouse_entered() -> void:
	if $Button/Profile:
		$Button/Profile.modulate = hover_color

func _on_profile_mouse_exited() -> void:
	if $Button/Profile:
		$Button/Profile.modulate = normal_color

func _on_profile_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton and event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
		print("个人资料按钮点击！")

# ==========================
# 消息按钮
# ==========================
func _on_message_mouse_entered() -> void:
	if $Button/Message:
		$Button/Message.modulate = hover_color

func _on_message_mouse_exited() -> void:
	if $Button/Message:
		$Button/Message.modulate = normal_color

func _on_message_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton and event.pressed and event.button_index == MOUSE_BUTTON_LEFT:
		print("消息按钮点击！")

# ==========================
# 关闭弹窗
# ==========================
func _on_submit_btn_gui_input(event: InputEvent) -> void:
	if event is InputEventMouseButton && event.pressed && event.button_index == MOUSE_BUTTON_LEFT:
		print("关闭登录窗口")
		login_popup.visible = false
		lock_bg.visible = false
