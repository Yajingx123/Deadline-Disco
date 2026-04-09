# SQL 文件总览

## 你需要什么

| 文件 | 作用 |
|------|------|
| `sql/001_schema.sql` | 建表：`word_books`、`words`、`word_book_words` |
| `sql/002_seed_wordbooks.sql` | 插入 6 本词书 |
| `sql/003_seed_words.sql` | 插入 20 个单词（含图片、释义、例句等） |
| `sql/004_seed_word_book_words.sql` | 插入词书-单词关联（支持重叠） |

**执行顺序**：001 → 002 → 003 → 004

---

## 表结构说明

### 1. `word_books`（词书）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| slug | varchar(32) | 唯一标识：daily, cs, mech, civil, traffic, math |
| title | varchar(128) | 词书标题 |
| description | text | 词书简介 |
| created_at, updated_at | datetime | 时间戳 |

### 2. `words`（单词，全局唯一）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | int | 主键 |
| word | varchar(64) | 单词 |
| phonetic | varchar(128) | 音标 |
| meaning | varchar(512) | 释义 |
| sentence | varchar(512) | 例句 |
| image_url | varchar(512) | 图片路径或 URL |
| audio_url | varchar(512) | 发音音频路径或 URL（可为空） |
| created_at, updated_at | datetime | 时间戳 |

### 3. `word_book_words`（词书-单词，多对多）

| 字段 | 类型 | 说明 |
|------|------|------|
| word_book_id | int | 词书 ID |
| word_id | int | 单词 ID |
| sort_order | int | 词书内排序 |

- 一个单词可属于多个词书（如 run 同时在 CS、Mechanical、Civil、Traffic、Math）
- 一个词书包含多个单词
- 主键：`(word_book_id, word_id)`

---

## 常用查询示例

```sql
-- 某词书下的全部单词（按 sort_order）
SELECT w.* FROM words w
JOIN word_book_words wbw ON w.id = wbw.word_id
WHERE wbw.word_book_id = 1
ORDER BY wbw.sort_order;

-- 某单词属于哪些词书
SELECT wb.* FROM word_books wb
JOIN word_book_words wbw ON wb.id = wbw.word_book_id
WHERE wbw.word_id = 11;
```

---

## 未来扩展（可选）

- **用户表** `users`：登录、昵称等
- **用户词书选择** `user_wordbook_selection`：替代 localStorage
- **学习进度** `user_word_progress`：每个用户对每个单词的掌握情况、最后练习时间等

需要时再增加对应 `.sql` 即可。
