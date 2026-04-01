extends Area2D

@export var target_scene: String = "res://Scenes/homepage/homepage.tscn"

func _on_body_entered(body):
	# 直接判断是不是全局 Player
	if body.name == "Player":
		AutoTransition.change_scene(target_scene)
