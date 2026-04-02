extends Node

# Web 导出：根据 index.html 的查询串在启动时切入学术星球（与浏览器返回 URL 对齐）
const ACADEMIC_SCENE := "res://Scenes/academic/Academic.tscn"


func _ready() -> void:
	if not OS.has_feature("web"):
		return
	call_deferred("_open_academic_if_query")


func _open_academic_if_query() -> void:
	var search_variant = JavaScriptBridge.eval("window.location.search", true)
	if search_variant == null:
		return
	var search := String(search_variant)
	if not _search_requests_academic(search):
		return
	await get_tree().process_frame
	if AutoTransition:
		await AutoTransition.change_scene(ACADEMIC_SCENE)


func _search_requests_academic(search: String) -> bool:
	if search.is_empty() or search == "?":
		return false
	var q := search.substr(1) if search.begins_with("?") else search
	for seg in q.split("&"):
		var kv := seg.split("=", false, 1)
		if kv.size() < 2:
			continue
		var k := String(kv[0]).strip_edges()
		var v := String(kv[1]).strip_edges()
		if k == "scene" and v == "academic":
			return true
	return false
