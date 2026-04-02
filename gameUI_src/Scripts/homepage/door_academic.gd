extends Area2D

# 唯一进入 Godot 内 academic.tscn 的门（原 training 门，文案统一为 Academic）

@export var tip_text: String = "Academic"
@export var door_id: String = "door_academic"

@onready var tip: Label = $Tip
var player: CharacterBody2D
var is_near: bool = false

func _ready() -> void:
	player = get_tree().get_first_node_in_group("Player")
	input_pickable = true
	tip.visible = false
	tip.text = tip_text
	body_entered.connect(_on_body_entered)
	body_exited.connect(_on_body_exited)
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
	if player:
		player.hide_and_disable()
	AutoTransition.change_scene("res://Scenes/academic/Academic.tscn")

func _is_player_body(body: Node2D) -> bool:
	return body == player or body.is_in_group("Player")

func _is_activate_key_event(event: InputEvent) -> bool:
	if event is InputEventKey:
		var key_event := event as InputEventKey
		if not key_event.pressed or key_event.echo:
			return false
		return key_event.is_action_pressed("enter") or key_event.is_action_pressed("ui_accept") or key_event.keycode == KEY_ENTER or key_event.keycode == KEY_KP_ENTER
	return event.is_action_pressed("enter") or event.is_action_pressed("ui_accept")
