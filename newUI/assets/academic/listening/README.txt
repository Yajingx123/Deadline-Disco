Listening 选视频页（understand / respond）— Godot 模式结构

页面：Academic-Practice/listening.html（?ui=godot / ui_mode cookie）
静态预览：Academic-Practice/listening-preview-static.html（双击，无服务）

布局要点
  · 无左侧 filter 栏
  · 外框 .listening-frame-outer：横竖比 4:3，尽量大；内框铺满内容区
  · 外/内红框之间：上留白 > 下留白（--listening-frame-pad-v-top 与 --listening-frame-pad-v-bottom）
  · .listening-center-wrap：.listening-square-stage | .listening-right-rail
  · 中间双红框内 3×3 视频格
  · .listening-filter-dock-frame：外长方形红框；装饰条 + .listening-filter-dock-scroll（内 5 槽：search…source）+ .listening-filter-slot--country + Reset
  · 最下 .listening-filter-reset-square：正方形红框，内 Reset（与主站 clearSmartFilters 同一按钮）

逻辑：六个控件 ID 与 practice-app-api.js initVideoList 一致；getVideos({ mode }) 区分 understand / respond 数据源；卡片与跳转详情页在 JS 内已按 mode 分支。
body/html 的 data-listening-mode：understand 红框强调，respond 青蓝强调（--listening-red）。

配色变量：game-listening-theme.css 内 --listening-red

去框线：body 设 data-listening-hide-slots
