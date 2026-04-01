extends Node2D

@onready var transition: ColorRect = $Transition
const DURATION = 0.3  # 稍微延长一点，让效果更明显

func _get_player():
	return get_node_or_null("/root/Player")

func _ready():
	# 进来时直接淡入
	transition.modulate.a = 1.0
	var t = create_tween()
	t.set_ease(Tween.EASE_IN_OUT)  # 添加缓动曲线，更平滑
	t.tween_property(transition, "modulate:a", 0.0, DURATION)
	
	# 确保过渡层始终在最上层
	transition.z_index = 100

# 全局调用这个！！
func change_scene(path: String):
	print("开始切换场景: ", path)  # 调试输出
	
	# 1. 确保过渡层可见
	transition.modulate.a = 0.0
	transition.visible = true
	
	# 2. 渐黑（使用缓动曲线）
	var t = create_tween()
	t.set_ease(Tween.EASE_IN_OUT)
	t.set_trans(Tween.TRANS_SINE)  # 使用正弦曲线，更平滑
	t.tween_property(transition, "modulate:a", 1.0, DURATION)
	await t.finished

	# 等画面完全渐暗后，再隐藏并冻结玩家
	var player = _get_player()
	if player and player.has_method("hide_and_disable"):
		player.hide_and_disable()
	
	# 3. 跳转场景
	var error = get_tree().change_scene_to_file(path)
	if error != OK:
		print("场景切换失败: ", path)
		return
	
	# 4. 等待新场景完全加载
	await get_tree().process_frame
	await get_tree().process_frame
	
	# 5. 确保过渡层还在（如果是 Autoload 就会在）
	transition.modulate.a = 1.0
	
	# 6. 淡入
	var t2 = create_tween()
	t2.set_ease(Tween.EASE_IN_OUT)
	t2.set_trans(Tween.TRANS_SINE)
	t2.tween_property(transition, "modulate:a", 0.0, DURATION)

	print("场景切换完成")
