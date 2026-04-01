extends Area2D

# 可编辑提示
@export var tip_text: String = "按 Enter 进门"

# 每扇门独立ID（你的未来接口）
@export var door_id: String = "door_default"

@onready var tip: Label = $Tip
var player: CharacterBody2D
var is_near: bool = false

func _ready():
	player = get_tree().get_first_node_in_group("Player")
	
	# 初始隐藏（和你原来一样）
	tip.visible = false
	tip.text = tip_text

	# 绑定信号（这是你原来能用的关键！）
	body_entered.connect(_on_body_entered)
	body_exited.connect(_on_body_exited)

# 靠近显示
func _on_body_entered(body: Node2D) -> void:
	if body == player:
		is_near = true
		tip.visible = true

# 离开隐藏
func _on_body_exited(body: Node2D) -> void:
	if body == player:
		is_near = false
		tip.visible = false

# 按 Enter 触发(Room.tscn)
#func _input(event: InputEvent) -> void:
	#if is_near && event.is_action_pressed("enter"):
		#print("✅ 门被触发：", door_id)
		#print("📝 提示文字：", tip_text)
		#AutoTransition.change_scene("res://Scenes/academic/academic.tscn")
		
func _input(event: InputEvent) -> void:
	if is_near && event.is_action_pressed("enter"):
		print("✅ 门被触发：", door_id)
		
		# 隐藏小人
		if player:
			player.hide_and_disable()
		
		# 跳转
		AutoTransition.change_scene("res://Scenes/academic/academic.tscn")
