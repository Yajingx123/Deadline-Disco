extends Area2D

# 可编辑提示
@export var tip_text: String = "按 Enter 进门"

# 每扇门独立ID（你的未来接口）
@export var door_id: String = "door_default"

# 与主项目 home.html 中 FORUM_URL 一致
const MAIN_FORUM_URL := "http://127.0.0.1:5173/?view=chooser&ui=godot"
const MAIN_TRAINING_URL := "http://127.0.0.1:8001/Academic-Practice/training.html?ui=godot"
const MAIN_STUDIO_URL := "http://127.0.0.1:8001/Studio/studio.html?ui=godot"
const MAIN_TECH_URL := "http://127.0.0.1:8001/technology.html?ui=godot"
const MAIN_COMPETITION_URL := "http://127.0.0.1:8001/home.html?module=Studio&ui=godot"

@onready var tip: Label = $Tip
var player: CharacterBody2D
var is_near: bool = false
var forum_dialog: ConfirmationDialog
var pending_url: String = ""

func _ready() -> void:
	player = get_tree().get_first_node_in_group("Player")
	input_pickable = true

	tip.visible = false
	tip.text = tip_text

	body_entered.connect(_on_body_entered)
	body_exited.connect(_on_body_exited)
	input_event.connect(_on_input_event)
	_setup_forum_dialog()

func _on_body_entered(body: Node2D) -> void:
	if _is_player_body(body):
		is_near = true
		tip.visible = true

func _on_body_exited(body: Node2D) -> void:
	if _is_player_body(body):
		is_near = false
		tip.visible = false

func _on_input_event(_viewport: Viewport, event: InputEvent, _shape_idx: int) -> void:
	if not is_near:
		return
	if event is InputEventMouseButton:
		var mb := event as InputEventMouseButton
		if mb.pressed and mb.button_index == MOUSE_BUTTON_LEFT:
			_activate_door()

func _input(event: InputEvent) -> void:
	if is_near and _is_activate_key_event(event):
		_activate_door()

func _unhandled_input(event: InputEvent) -> void:
	if is_near and _is_activate_key_event(event):
		_activate_door()

func _activate_door() -> void:
	print("✅ 门被触发：", door_id, " tip=", tip_text)
	var normalized_tip := String(tip_text).strip_edges().to_lower()
	if normalized_tip == "forum":
		_open_forum_with_prompt()
	else:
		var target_url := _target_url_for_tip(normalized_tip)
		if target_url != "":
			_navigate_to_url(target_url)
		else:
			print("📝 暂无映射：", tip_text)

func _open_forum_with_prompt() -> void:
	if forum_dialog and forum_dialog.visible:
		return
	pending_url = MAIN_FORUM_URL
	if forum_dialog and forum_dialog.is_inside_tree():
		forum_dialog.popup_centered(Vector2i(860, 500))
	else:
		_navigate_to_url(pending_url)
		pending_url = ""

func _navigate_to_url(url: String) -> void:
	if OS.has_feature("web"):
		JavaScriptBridge.eval("window.location.href = '" + url + "';")
	else:
		OS.shell_open(url)

func _setup_forum_dialog() -> void:
	forum_dialog = ConfirmationDialog.new()
	forum_dialog.title = "Forum Portal"
	forum_dialog.dialog_text = "You are at the Forum gate.\\nEnter the Forum section now?"
	forum_dialog.ok_button_text = "Enter Forum"
	forum_dialog.exclusive = true
	forum_dialog.unresizable = true
	forum_dialog.custom_minimum_size = Vector2(820, 460)
	get_tree().root.call_deferred("add_child", forum_dialog)
	forum_dialog.get_cancel_button().text = "Stay Here"
	forum_dialog.confirmed.connect(_on_forum_dialog_confirmed)

func _on_forum_dialog_confirmed() -> void:
	if pending_url != "":
		_navigate_to_url(pending_url)
		pending_url = ""

func _is_player_body(body: Node2D) -> bool:
	return body == player or body.is_in_group("Player")

func _is_activate_key_event(event: InputEvent) -> bool:
	if event is InputEventKey:
		var key_event := event as InputEventKey
		if not key_event.pressed or key_event.echo:
			return false
		return key_event.is_action_pressed("enter") or key_event.is_action_pressed("ui_accept") or key_event.keycode == KEY_ENTER or key_event.keycode == KEY_KP_ENTER
	return event.is_action_pressed("enter") or event.is_action_pressed("ui_accept")

func _target_url_for_tip(normalized_tip: String) -> String:
	match normalized_tip:
		"training":
			return MAIN_TRAINING_URL
		"game studio":
			return MAIN_STUDIO_URL
		"technic":
			return MAIN_TECH_URL
		"team competition":
			return MAIN_COMPETITION_URL
		"teamwork":
			return MAIN_COMPETITION_URL
		_:
			return ""
