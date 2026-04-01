extends Node2D

@export var hover_scale: float = 1.1
@export var tween_duration: float = 0.15

var original_scales = {}
var planet_labels = {}  # 先声明为空字典


func _ready():
	pass


# ==========================
# 通用缩放动画
# ==========================
func _scale_to(planet: TextureRect, target_scale: float) -> void:
	if not original_scales.has(planet):
		original_scales[planet] = planet.scale

	var final_scale = original_scales[planet] * target_scale

	var tween = create_tween()
	tween.tween_property(planet, "scale", final_scale, tween_duration)
	tween.set_ease(Tween.EASE_OUT)


# ==========================
# 显示/隐藏文字
# ==========================
func _show_label(planet: TextureRect) -> void:
	# 获取星球编号
	var index = planet.name.replace("Planet", "")
	var label = get_node("Label" + index)
	
	# 设置文字内容
	if planet_labels.has(planet):
		label.text = planet_labels[planet]
	label.visible = true

func _hide_label(planet: TextureRect) -> void:
	var index = planet.name.replace("Planet", "")
	var label = get_node("Label" + index)
	label.visible = false


# ==========================
# 星球1
# ==========================
func _on_planet_1_mouse_entered() -> void:
	_scale_to($Planet1, hover_scale)
	_show_label($Planet1)

func _on_planet_1_mouse_exited() -> void:
	_scale_to($Planet1, 1.0)
	_hide_label($Planet1)


# ==========================
# 星球2
# ==========================
func _on_planet_2_mouse_entered() -> void:
	_scale_to($Planet2, hover_scale)
	_show_label($Planet2)

func _on_planet_2_mouse_exited() -> void:
	_scale_to($Planet2, 1.0)
	_hide_label($Planet2)


# ==========================
# 星球3
# ==========================
func _on_planet_3_mouse_entered() -> void:
	_scale_to($Planet3, hover_scale)
	_show_label($Planet3)

func _on_planet_3_mouse_exited() -> void:
	_scale_to($Planet3, 1.0)
	_hide_label($Planet3)


# ==========================
# 星球4
# ==========================
func _on_planet_4_mouse_entered() -> void:
	_scale_to($Planet4, hover_scale)
	_show_label($Planet4)

func _on_planet_4_mouse_exited() -> void:
	_scale_to($Planet4, 1.0)
	_hide_label($Planet4)
