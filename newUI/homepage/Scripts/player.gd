extends CharacterBody2D

@export var move_speed : float = 30
@export var animator : AnimatedSprite2D

# Called every frame. 'delta' is the elapsed time since the previous frame.
func _physics_process(delta: float) -> void:
	velocity = Input.get_vector("left", "right", "up", "down") * move_speed
	
	if velocity.x > 0:        # 往右走 → 不翻转
		animator.flip_h = false
	elif velocity.x < 0:      # 往左走 → 水平镜像翻转
		animator.flip_h = true
		
	# 如果速度为零，播放待机动画
	if velocity == Vector2.ZERO:
		animator.play("idle")
	#如果速度不为零，播放走路动画
	else:
		animator.play("walking")
		
	#move_and_slide()
