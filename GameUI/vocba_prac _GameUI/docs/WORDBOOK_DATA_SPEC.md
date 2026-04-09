# Word book data storage design (词书与单词数据存储说明)

当前前端不实现真实数据，只做 UI。等你接入词库时，可按下面方式组织数据。

---

## 1. 两类数据

| 类型 | 作用 | 主要内容 |
|------|------|----------|
| **词书 (Word book)** | 一本“书”的元信息，以及它包含哪些词 | id、标题、词数、分类/标签、简介 |
| **词条 (Word entry)** | 一个单词的全部学习内容 | 单词、音标、释义、例句、图片、音频等 |

词书与词条的关系：**多对多**。一个词可以出现在多本词书里（例如既在 Daily 又在 CS）；一本词书包含多个词条。

---

## 2. 词书 (Word book) 结构建议

每条词书一条记录，例如：

```json
{
  "id": "daily",
  "title": "Daily & campus vocabulary",
  "titleEn": "Daily & campus vocabulary",
  "wordCount": 420,
  "tags": ["All majors", "Campus life", "Group discussion", "Study planning"],
  "description": "For all majors. Group discussion, campus life, study planning.",
  "introLong": "This book is for everyone. It covers everyday and campus vocabulary..."
}
```

- **id**：唯一标识，和当前前端的 `daily | cs | mech | civil | traffic | math` 一致即可。
- **title / titleEn**：展示用标题（可只有其一）。
- **wordCount**：词数（可从词条表统计，这里可做缓存）。
- **tags**：分类/用途标签（如专业词汇、小组讨论词汇）。
- **description / introLong**：短描述与详情页长介绍。

**“包含哪些词”的两种常见做法：**

- **方式 A**：词书只存元信息，词条里存 `bookIds: ["daily", "cs"]`，表示该词属于哪些书。查某本书的词 = 查 `bookIds` 包含该书 id 的所有词条。
- **方式 B**：词书多一个字段 `wordIds: ["w1", "w2", ...]`，直接存词条 id 列表。维护时要注意增删词时同步更新。

推荐 **方式 A**，改词书范围时只改词条，不用改书。

---

## 3. 词条 (Word entry) 结构建议

每个单词一条记录，例如：

```json
{
  "id": "w_daily_001",
  "word": "collaborate",
  "phonetic": "/kəˈlæbəreɪt/",
  "meaning": "v. to work together with others",
  "sentence": "Students collaborate in small groups to solve real problems.",
  "sentenceAudioUrl": "https://your-cdn.com/audio/sentences/collaborate.mp3",
  "wordAudioUrl": "https://your-cdn.com/audio/words/collaborate.mp3",
  "imageUrl": "https://your-cdn.com/images/collaborate.jpg",
  "bookIds": ["daily", "cs"],
  "tags": ["Group Discussion Vocabulary", "Major Vocabulary"]
}
```

字段说明：

| 字段 | 含义 | 备注 |
|------|------|------|
| **id** | 词条唯一 id | 方便引用、去重、进度追踪 |
| **word** | 单词拼写 | 必填 |
| **phonetic** | 音标 | 可选 |
| **meaning** | 释义 | 必填 |
| **sentence** | 例句 | 与图片一起做“图–句”匹配 |
| **sentenceAudioUrl** | 例句朗读音频 | 可选，可和 wordAudioUrl 二选一或都有 |
| **wordAudioUrl** | 单词发音音频 | 用于“听音选词”等 |
| **imageUrl** | 配图 URL | 用于“看图选词/配句” |
| **bookIds** | 所属词书 id 列表 | 多对多：一词多书 |
| **tags** | 用途标签 | 如 Major Vocabulary、Group Discussion Vocabulary |

可选扩展（以后需要再加）：

- `imageAlt`：图片说明（无障碍）
- `example2`、`example3`：多例句
- `difficulty`：难度等级
- `notes`：备注

---

## 4. 存储方式选型（不实现，只做规划）

- **静态 JSON**：每个词书一个文件，如 `data/books/daily.json` 里是该书元信息 + `words` 数组（或只存 `wordIds`，词条在 `data/words.json` 里按 id 查）。前端用 fetch 按需加载。
- **本地/后端数据库**：  
  - 表 `word_books`：id, title, word_count, tags, description, intro_long 等。  
  - 表 `word_entries`：id, word, phonetic, meaning, sentence, sentence_audio_url, word_audio_url, image_url, tags 等。  
  - 表 `word_book_entries`：book_id, word_id（多对多关联），或只在词条里存 `book_ids` 数组。
- **CMS / 后台**：词书和词条在后台维护，通过 API 返回 JSON，前端不变，只把写死的 BOOKS/词表换成接口数据。

图片和音频可以：

- 放在 CDN 或静态资源目录，词条里只存 URL；
- 或存文件路径/对象存储 key，由后端或前端拼出可访问 URL。

---

## 5. 和当前前端的对应关系

- **词书列表**：`wordbank.html` / `wordbook-detail.html` 里的 `BOOKS` 对象，以后可改为从接口或 JSON 加载，结构对上即可。
- **“当前选中的词书”**：已用 localStorage `vocab_wordbook_selection` 存 id 数组，练习时只拉取这些 id 对应的词书下的词条即可。
- **练习用词**：按当前选中的 `bookIds` 过滤出词条（或按 bookIds 查关联表），再按需做分页、随机、难度过滤等。

这样你之后只要：  
1）按上述结构准备好词书 + 词条（单词–图片–音频–sentence 等）；  
2）用 JSON 或数据库存；  
3）前端把原来的写死数据改成请求这些数据即可，无需改存储设计。
