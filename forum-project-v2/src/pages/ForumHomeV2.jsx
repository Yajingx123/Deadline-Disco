import { useEffect, useMemo, useState } from 'react'
import { createPost, fetchLabels, fetchPostDetail, fetchPosts, fetchUserPosts, incrementPostViews } from '../api/forumApi'
import PostDetail from './PostDetail'
import PostModal from '../components/PostModal'

const SORT_OPTIONS = [
  { label: 'Latest', value: 'latest_post' },
  { label: 'Hot', value: 'hot' },
  { label: 'Most Commented', value: 'comments' },
]

const SAMPLE_LABELS = [
  { id: 'sample-l-1', name: 'Study Tips' },
  { id: 'sample-l-2', name: 'Exam Prep' },
  { id: 'sample-l-3', name: 'Campus Life' },
  { id: 'sample-l-4', name: 'Language Lab' },
]

const SAMPLE_POSTS = [
  {
    id: 900001,
    title: 'Sample: How I finished revision in 10 days',
    content: 'I split the syllabus by difficulty and reviewed weak topics every morning.',
    time: 'Sample',
    views: 241,
    commentCount: 18,
    likeCount: 36,
    normalizedLabels: ['Study Tips', 'Exam Prep'],
    isSample: true,
    canOpenDetail: true,
  },
  {
    id: 900002,
    title: 'Sample: Listening practice resource list',
    content: 'Collected podcasts and dictation drills with level tags for quick daily training.',
    time: 'Sample',
    views: 133,
    commentCount: 9,
    likeCount: 24,
    normalizedLabels: ['Language Lab'],
    isSample: true,
    canOpenDetail: true,
  },
  {
    id: 900003,
    title: 'Sample: Quiet places to study on campus',
    content: 'Sharing a map of low-noise rooms and best hours before peak traffic.',
    time: 'Sample',
    views: 88,
    commentCount: 7,
    likeCount: 12,
    normalizedLabels: ['Campus Life', 'Study Tips'],
    isSample: true,
    canOpenDetail: true,
  },
]

function getSummary(text = '', limit = 140) {
  const plain = String(text).replace(/\s+/g, ' ').trim()
  if (!plain) return 'No summary yet.'
  if (plain.length <= limit) return plain
  return `${plain.slice(0, limit)}...`
}

function normalizeLabels(post) {
  const raw = post?.labels
  if (!raw) return []
  if (Array.isArray(raw)) {
    return raw
      .map((item) => {
        if (typeof item === 'string') return item
        return item?.name || item?.label || ''
      })
      .filter(Boolean)
  }
  if (typeof raw === 'string') {
    return raw.split(',').map((item) => item.trim()).filter(Boolean)
  }
  return []
}

function bySort(a, b, sort) {
  if (sort === 'comments') {
    return Number(b.commentCount || 0) - Number(a.commentCount || 0)
  }
  if (sort === 'hot') {
    const scoreA = Number(a.likeCount || 0) * 2 + Number(a.commentCount || 0) + Number(a.views || 0) * 0.1
    const scoreB = Number(b.likeCount || 0) * 2 + Number(b.commentCount || 0) + Number(b.views || 0) * 0.1
    return scoreB - scoreA
  }
  return Number(b.id || 0) - Number(a.id || 0)
}

export default function ForumHomeV2() {
  const [labels, setLabels] = useState([])
  const [currentUser, setCurrentUser] = useState(null)
  const [posts, setPosts] = useState([])
  const [query, setQuery] = useState('')
  const [selectedTags, setSelectedTags] = useState([])
  const [sort, setSort] = useState('latest_post')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showComposer, setShowComposer] = useState(false)
  const [selectedPost, setSelectedPost] = useState(null)
  const [viewMode, setViewMode] = useState('all')

  const loadData = async () => {
    setLoading(true)
    setError('')
    try {
      const labelPromise = fetchLabels()
      const postPromise = viewMode === 'mine'
        ? fetchUserPosts()
        : fetchPosts({ q: query, labels: selectedTags, sort })

      const [labelData, postData] = await Promise.all([labelPromise, postPromise])
      const postRows = postData.posts || []

      let prepared = postRows.map((post) => ({
        ...post,
        normalizedLabels: normalizeLabels(post),
        summary: getSummary(post.content),
      }))

      if (viewMode === 'mine') {
        if (query.trim()) {
          const q = query.trim().toLowerCase()
          prepared = prepared.filter((post) => {
            const title = String(post.title || '').toLowerCase()
            const content = String(post.content || '').toLowerCase()
            return title.includes(q) || content.includes(q)
          })
        }

        if (selectedTags.length > 0) {
          prepared = prepared.filter((post) => {
            const tagSet = new Set(post.normalizedLabels)
            return selectedTags.every((tag) => tagSet.has(tag))
          })
        }

        prepared.sort((a, b) => bySort(a, b, sort))
      }

      setLabels(labelData.labels || [])
      setCurrentUser(labelData.currentUser || null)
      setPosts(prepared)
    } catch (err) {
      setError(err.message || 'Failed to load forum.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(loadData, 200)
    return () => window.clearTimeout(timer)
  }, [query, sort, selectedTags.join(','), viewMode])

  const toggleTag = (name) => {
    setSelectedTags((prev) => {
      if (prev.includes(name)) {
        return prev.filter((item) => item !== name)
      }
      return [...prev, name]
    })
  }

  const openPost = async (post) => {
    const postId = Number(post?.id || 0)
    if (post?.isSample && post?.canOpenDetail) {
      setSelectedPost({
        ...post,
        author: 'Sample Author',
        publishTime: 'Sample',
      })
      const params = new URLSearchParams(window.location.search)
      params.set('postId', String(postId))
      window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`)
      return
    }
    try {
      await incrementPostViews(postId)
      const detail = await fetchPostDetail(postId)
      setSelectedPost(detail.post || null)
      const params = new URLSearchParams(window.location.search)
      params.set('postId', String(postId))
      window.history.replaceState({}, '', `${window.location.pathname}?${params.toString()}`)
      setPosts((prev) => prev.map((post) => (
        post.id === postId ? { ...post, views: Number(post.views || 0) + 1 } : post
      )))
    } catch (err) {
      setError(err.message || 'Failed to load post detail.')
    }
  }

  const submitPost = async (payload) => {
    await createPost(payload)
    await loadData()
  }

  const displayLabels = useMemo(() => {
    const map = new Map()
    labels.forEach((label) => map.set(label.name, label))
    SAMPLE_LABELS.forEach((label) => {
      if (!map.has(label.name)) {
        map.set(label.name, label)
      }
    })
    return [...map.values()]
  }, [labels])

  const displayPosts = useMemo(() => {
    const q = query.trim().toLowerCase()
    let demo = SAMPLE_POSTS.map((post) => ({
      ...post,
      summary: getSummary(post.content),
    }))

    if (q) {
      demo = demo.filter((post) => {
        const title = String(post.title || '').toLowerCase()
        const content = String(post.content || '').toLowerCase()
        return title.includes(q) || content.includes(q)
      })
    }

    if (selectedTags.length > 0) {
      demo = demo.filter((post) => selectedTags.every((tag) => post.normalizedLabels.includes(tag)))
    }

    demo.sort((a, b) => bySort(a, b, sort))
    if (viewMode !== 'all') {
      return posts
    }
    if (error) {
      return demo
    }
    return [...demo, ...posts]
  }, [posts, query, selectedTags, sort, viewMode, error])

  const handleBackToList = () => {
    setSelectedPost(null)
    const params = new URLSearchParams(window.location.search)
    params.delete('postId')
    const queryString = params.toString()
    window.history.replaceState({}, '', `${window.location.pathname}${queryString ? `?${queryString}` : ''}`)
  }

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const postId = Number(params.get('postId') || 0)
    if (!postId) return

    const sample = SAMPLE_POSTS.find((item) => item.id === postId && item.canOpenDetail)
    if (sample) {
      setSelectedPost({
        ...sample,
        author: 'Sample Author',
        publishTime: 'Sample',
      })
      return
    }

    fetchPostDetail(postId)
      .then((data) => {
        if (data?.post) {
          setSelectedPost(data.post)
        }
      })
      .catch(() => {})
  }, [])

  return (
    <div className="v2-viewport">
      {selectedPost ? (
        <div className="v2-detail-route">
          <PostDetail post={selectedPost} onBack={handleBackToList} />
        </div>
      ) : (
        <div className="v2-stage">
          <header className="v2-page-title">Forum</header>

          <aside className="v2-label-zone">
            <div className="v2-label-panel">
              <div className="v2-label-head">
                <h3>Labels</h3>
              </div>
              <div className="v2-chip-wrap v2-chip-wrap--side">
                {displayLabels.map((label) => (
                  <button
                    key={label.id}
                    className={`v2-chip ${selectedTags.includes(label.name) ? 'v2-chip--on' : ''}`}
                    onClick={() => toggleTag(label.name)}
                  >
                    {label.name}
                  </button>
                ))}
              </div>
            </div>
          </aside>

          <section className="v2-content-zone">
            <div className="v2-board">
              <section className="v2-main-column">
                <div className="v2-toolbar">
                  <input
                    className="v2-input"
                    placeholder="Search thread"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                  />
                  <select className="v2-select" value={sort} onChange={(e) => setSort(e.target.value)}>
                    {SORT_OPTIONS.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                  </select>
                </div>

                <div className="v2-feed-shell">
                  {loading ? <div className="v2-state">Loading...</div> : null}
                  {error ? <div className="v2-state v2-state--error">{error}</div> : null}

                  {!loading ? (
                    <div className="v2-feed-scroll">
                      {displayPosts.map((post) => (
                        <article
                          className={`v2-card ${post.isSample && !post.canOpenDetail ? 'v2-card--sample' : ''}`}
                          key={post.id}
                          onClick={(post.isSample && !post.canOpenDetail) ? undefined : () => openPost(post)}
                        >
                          <div className="v2-card-top">
                            <strong>{post.title}</strong>
                            <span>{post.time || 'now'}</span>
                          </div>
                          <p>{post.summary}</p>
                          <div className="v2-meta">
                            <span>Views {post.views || 0}</span>
                            <span>Comments {post.commentCount || 0}</span>
                            <span>Likes {post.likeCount || 0}</span>
                          </div>
                        </article>
                      ))}
                      {displayPosts.length === 0 ? <div className="v2-state">No posts found.</div> : null}
                    </div>
                  ) : null}
                </div>
              </section>
            </div>
          </section>

          <div className="v2-fab-stack">
            <button className="v2-fab v2-fab--post" aria-label="Create Post" onClick={() => setShowComposer(true)} />
            <button
              aria-label="Toggle My Posts"
              className={`v2-fab v2-fab--mine ${viewMode === 'mine' ? 'v2-fab--active' : ''}`}
              onClick={() => setViewMode((prev) => (prev === 'mine' ? 'all' : 'mine'))}
            />
          </div>
        </div>
      )}

      <PostModal
        isOpen={showComposer}
        onClose={() => setShowComposer(false)}
        onSubmit={submitPost}
        isReplyMode={false}
        quoteText=""
        parentTitle=""
        labelOptions={displayLabels}
        currentUser={currentUser}
      />
    </div>
  )
}
