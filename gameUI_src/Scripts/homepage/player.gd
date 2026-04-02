extends CharacterBody2D

@export var move_speed : float = 300
@export var animator : AnimatedSprite2D

# 移动模式：0=homepage不移动，1=room自由移动
@export var move_mode: int = 0

func _ready():
	# 监听场景切换，自动定位到SpawnPoint
	get_tree().scene_changed.connect(_on_scene_changed)
	await get_tree().process_frame
	_on_scene_changed()

func _on_scene_changed() -> void:
	await get_tree().process_frame
	var scene := get_tree().current_scene
	if scene == null:
		return

	var scene_path := scene.scene_file_path.to_lower()
	var scene_name := scene.name.to_lower()
	var is_homepage := scene_path.ends_with("homepage.tscn") or scene_name == "homepage"
	var is_academic := scene_path.ends_with("academic.tscn") or scene_name == "academic"
	
	# 根据场景文件或根节点名称设置模式，避免大小写不一致导致判断失败
	if is_homepage:
		move_mode = 0  # homepage：不移动
	else:
		move_mode = 1  # 其他场景：可以移动
	
	# 传送到SpawnPoint
	if scene.has_node("SpawnPoint"):
		var spawn = scene.get_node("SpawnPoint")
		global_position = spawn.global_position

	if is_academic:
		hide_and_disable()
	else:
		show_and_enable()

func _physics_process(delta: float) -> void:
	var input_dir = Input.get_vector("left", "right", "up", "down")
	
	if move_mode == 0:
		# homepage：完全禁用移动，只播放动画
		# 保留输入方向给其他脚本读取，但不实际调用 move_and_slide()
		velocity = input_dir * move_speed
		
		# 根据左右输入控制动画
		if input_dir.x > 0:
			animator.flip_h = false
			animator.play("walking")
		elif input_dir.x < 0:
			animator.flip_h = true
			animator.play("walking")
		else:
			animator.play("idle")
		
		# 关键：不调用 move_and_slide()，所以不会移动
		# 直接返回，跳过移动逻辑
		return
	
	else:
		# 房间模式：正常移动
		velocity = input_dir * move_speed
		
		# 动画控制
		if velocity.x > 0:
			animator.flip_h = false
		elif velocity.x < 0:
			animator.flip_h = true
		
		if velocity.length_squared() > 0:
			animator.play("walking")
		else:
			animator.play("idle")
		
		# 只在房间模式调用 move_and_slide()
		move_and_slide()

func hide_and_disable():
	velocity = Vector2.ZERO
	visible = false
	set_process(false)
	set_physics_process(false)

# 显示小人 + 恢复移动
func show_and_enable():
	visible = true
	if animator:
		animator.visible = true
	set_process(true)
	set_physics_process(true)
