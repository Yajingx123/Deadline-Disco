extends Node2D

# 与主站 Academic-Practice/training.html 四个卡片一致（功能不变，仅入口在 Godot）
# 本地 URL 与 shared/acadbeat-local-config.js（mainOrigin 等）对齐；见 docs/ARCHITECTURE.md

const DEV_MAIN_ORIGIN := "http://127.0.0.1:8001"
const DEV_GAME_PORT := "5500"
const PATH_VOCAB := "/vocba_prac/?ui=godot"
const PATH_LISTEN_UNDERSTAND := "/Academic-Practice/listening.html?mode=understand&ui=godot"
const PATH_LISTEN_RESPOND := "/Academic-Practice/listening.html?mode=respond&ui=godot"
const PATH_VOICE_ROOM := "/Academic-Practice/voice_room.html?ui=godot"

@export var hover_scale: float = 1.1
@export var tween_duration: float = 0.15

var original_scales = {}
var planet_labels = {}


func _ready() -> void:
	pass


func _main_origin() -> String:
	if not OS.has_feature("web"):
		return DEV_MAIN_ORIGIN

	var port_variant = JavaScriptBridge.eval("window.location.port", true)
	var host_variant = JavaScriptBridge.eval("window.location.hostname", true)
	var origin_variant = JavaScriptBridge.eval("window.location.origin", true)

	var port := String(port_variant if port_variant != null else "")
	var host := String(host_variant if host_variant != null else "127.0.0.1")
	var origin := String(origin_variant if origin_variant != null else "")

	if port == DEV_GAME_PORT:
		return "http://" + host + ":8001"
	if not origin.is_empty():
		return origin
	return DEV_MAIN_ORIGIN


func _build_main_url(path_with_query: String) -> String:
	return _main_origin() + path_with_query


func _try_open_planet(which: int) -> void:
	var url := ""
	match which:
		1:
			url = _build_main_url(PATH_VOCAB)
		2:
			url = _build_main_url(PATH_LISTEN_UNDERSTAND)
		3:
			url = _build_main_url(PATH_LISTEN_RESPOND)
		4:
			url = _build_main_url(PATH_VOICE_ROOM)
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
