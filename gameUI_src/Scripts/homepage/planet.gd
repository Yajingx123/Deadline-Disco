extends Node2D

# 旋转速度（度/秒），可在编辑器里调整
@export var rotate_speed: float = 50.0

# 全局获取Player，不再用场景路径
var player: CharacterBody2D

func _ready():
	# 从Autoload全局获取Player节点
	player = get_node("/root/Player")

func _process(delta):
	# 只有当小人存在且在移动时才旋转
	if not player:
		return  # 安全检查，防止null报错
	
	if player.velocity.length() > 0.1:
		# 核心：根据小人 X 轴方向决定星球旋转方向
		if player.velocity.x > 0:
			# 往右走 → 顺时针转
			rotation -= deg_to_rad(rotate_speed) * delta
		elif player.velocity.x < 0:
			# 往左走 → 逆时针转
			rotation += deg_to_rad(rotate_speed) * delta
