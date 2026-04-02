extends Node2D

# 与主站 Academic-Practice/training.html 四个卡片一致（功能不变，仅入口在 Godot）
# 本地 URL 与 shared/acadbeat-local-config.js（mainOrigin 等）对齐；见 docs/ARCHITECTURE.md

const URL_VOCAB := "http://127.0.0.1:8001/vocba_prac/?ui=godot"
const URL_LISTEN_UNDERSTAND := "http://127.0.0.1:8001/Academic-Practice/listening.html?mode=understand&ui=godot"
const URL_LISTEN_RESPOND := "http://127.0.0.1:8001/Academic-Practice/listening.html?mode=respond&ui=godot"
const URL_VOICE_ROOM := "http://127.0.0.1:8001/Academic-Practice/voice_room.html?ui=godot"

@export var hover_scale: float = 1.1
@export var tween_duration: float = 0.15

var original_scales = {}
var planet_labels = {}


func _ready() -> void:
	pass


func _try_open_planet(which: int) -> void:
	var url := ""
	match which:
		1:
			url = URL_VOCAB
		2:
			url = URL_LISTEN_UNDERSTAND
		3:
			url = URL_LISTEN_RESPOND
		4:
			url = URL_VOICE_ROOM
	if url != "":
		ExternalLink.open(url)


func _is_left_click(event: InputEvent) -> bool:
	if event is InputEventMouseButton:
		var mb := event as InputEventMouseButton
		return mb.pressed and mb.button_index == MOUSE_BUTTON_LEFT
	return false


func _on_planet_1_gui_input(event: InputEvent) -> void:
	if _is_left_click(event):
		_try_open_planet(1)


func _on_planet_2_gui_input(event: InputEvent) -> void:
	if _is_left_click(event):
		_try_open_planet(2)


func _on_planet_3_gui_input(event: InputEvent) -> void:
	if _is_left_click(event):
		_try_open_planet(3)


func _on_planet_4_gui_input(event: InputEvent) -> void:
	if _is_left_click(event):
		_try_open_planet(4)


func _scale_to(planet: TextureRect, target_scale: float) -> void:
	if not original_scales.has(planet):
		original_scales[planet] = planet.scale

	var final_scale = original_scales[planet] * target_scale

	var tween = create_tween()
	tween.tween_property(planet, "scale", final_scale, tween_duration)
	tween.set_ease(Tween.EASE_OUT)


func _show_label(planet: TextureRect) -> void:
	var index = planet.name.replace("Planet", "")
	var label = get_node("Label" + index)

	if planet_labels.has(planet):
		label.text = planet_labels[planet]
	label.visible = true


func _hide_label(planet: TextureRect) -> void:
	var index = planet.name.replace("Planet", "")
	var label = get_node("Label" + index)
	label.visible = false


func _on_planet_1_mouse_entered() -> void:
	_scale_to($Planet1, hover_scale)
	_show_label($Planet1)


func _on_planet_1_mouse_exited() -> void:
	_scale_to($Planet1, 1.0)
	_hide_label($Planet1)


func _on_planet_2_mouse_entered() -> void:
	_scale_to($Planet2, hover_scale)
	_show_label($Planet2)


func _on_planet_2_mouse_exited() -> void:
	_scale_to($Planet2, 1.0)
	_hide_label($Planet2)


func _on_planet_3_mouse_entered() -> void:
	_scale_to($Planet3, hover_scale)
	_show_label($Planet3)


func _on_planet_3_mouse_exited() -> void:
	_scale_to($Planet3, 1.0)
	_hide_label($Planet3)


func _on_planet_4_mouse_entered() -> void:
	_scale_to($Planet4, hover_scale)
	_show_label($Planet4)


func _on_planet_4_mouse_exited() -> void:
	_scale_to($Planet4, 1.0)
	_hide_label($Planet4)
