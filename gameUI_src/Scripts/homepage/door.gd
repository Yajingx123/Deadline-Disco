extends Area2D

# 外链门：除 Academic 门外，场景中均使用本脚本
# 本地绝对 URL 与 shared/acadbeat-local-config.js 保持一致（改端口时两处同步）；见 docs/ARCHITECTURE.md

@export var tip_text: String = "按 Enter 进门"
@export var door_id: String = "door_default"

const MAIN_FORUM_URL := "http://127.0.0.1:5173/forum-project/dist/?view=chooser&ui=godot"
const MAIN_STUDIO_URL := "http://127.0.0.1:8001/Studio/studio.html?ui=godot"
const MAIN_TECH_URL := "http://127.0.0.1:8001/technology.html?ui=godot"
const MAIN_COMPETITION_URL := "http://127.0.0.1:8001/home.html?module=Studio&ui=godot"

@onready var tip: Label = $Tip
var player: CharacterBody2D
var is_near: bool = false

func _ready() -> void:
	player = get_tree().get_first_node_in_group("Player")
	input_pickable = true
	tip.visible = false
	tip.text = tip_text
	# body_entered / body_exited 在 Planet.tscn 里已连接，勿在此重复 connect，否则会报 Signal already connected
	input_event.connect(_on_input_event)

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
	var normalized_tip := String(tip_text).strip_edges().to_lower()
	var target_url := _target_url_for_tip(normalized_tip)
	if target_url != "":
		ExternalLink.open(target_url)
	else:
		print("📝 暂无外链映射：", tip_text)

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
		"forum":
			return MAIN_FORUM_URL
		"game studio":
			return MAIN_STUDIO_URL
		"technic", "technologies":
			return MAIN_TECH_URL
		"team competition", "teamwork":
			return MAIN_COMPETITION_URL
		_:
			return ""
