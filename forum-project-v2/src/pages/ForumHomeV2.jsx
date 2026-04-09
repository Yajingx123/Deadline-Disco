import { useEffect, useMemo, useState } from 'react'
import { createComment, createPost, fetchLabels, fetchPostDetail, fetchPosts, fetchUserFavorites, fetchUserLikes, fetchUserPosts, incrementPostViews } from '../api/forumApi'
import PostDetail from './PostDetail'
import PostModal from '../components/PostModal'

const SORT_OPTIONS = [
  { label: 'Latest', value: 'latest_post' },
  { label: 'Hot', value: 'hot' },
  { label: 'Most Commented', value: 'comments' },
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
  const [favorites, setFavorites] = useState([])
  const [likes, setLikes] = useState([])
  const [userPosts, setUserPosts] = useState([])
  const [query, setQuery] = useState('')
  const [selectedTags, setSelectedTags] = useState([])
  const [sort, setSort] = useState('latest_post')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [showComposer, setShowComposer] = useState(false)
  const [selectedPost, setSelectedPost] = useState(null)
  const [viewMode, setViewMode] = useState('all') // all | favorites
  const [favoritesTab, setFavoritesTab] = useState('favorites') // favorites | likes | posts

  function preparePosts(rawRows = []) {
    return rawRows.map((post) => ({
      ...post,
      normalizedLabels: normalizeLabels(post),
      summary: getSummary(post.content),
    }))
  }

  function applyLocalFilters(rows = []) {
    let next = rows
    const q = query.trim().toLowerCase()
    if (q) {
      next = next.filter((post) => {
        const title = String(post.title || '').toLowerCase()
        const content = String(post.content || '').toLowerCase()
        return title.includes(q) || content.includes(q)
      })
    }
    if (selectedTags.length > 0) {
      next = next.filter((post) => {
        const tagSet = new Set(post.normalizedLabels || [])
        return selectedTags.every((tag) => tagSet.has(tag))
      })
    }
    return [...next].sort((a, b) => bySort(a, b, sort))
  }

  const loadData = async () => {
    setLoading(true)
    setError('')
    try {
      if (viewMode === 'all') {
        const [labelData, postData] = await Promise.all([
          fetchLabels(),
          fetchPosts({ q: query, labels: selectedTags, sort }),
        ])
        setLabels(labelData.labels || [])
        setCurrentUser(labelData.currentUser || null)
        setPosts(preparePosts(postData.posts || []))
      } else {
        const [labelData, favoritesData, likesData, userPostsData] = await Promise.all([
          fetchLabels(),
          fetchUserFavorites(),
          fetchUserLikes(),
          fetchUserPosts(),
        ])
        setLabels(labelData.labels || [])
        setCurrentUser(labelData.currentUser || null)
        setFavorites(applyLocalFilters(preparePosts(favoritesData.posts || [])))
        setLikes(applyLocalFilters(preparePosts(likesData.posts || [])))
        setUserPosts(applyLocalFilters(preparePosts(userPostsData.posts || [])))
      }
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

  const handleAddComment = async ({ postId, content, parentCommentId = null }) => {
    await createComment({
      postId,
      content,
      parentCommentId,
    })
    const detail = await fetchPostDetail(postId)
    if (detail?.post) {
      setSelectedPost(detail.post)
    }
    await loadData()
  }

  const handleLikeChange = (postId, liked, nextLikeCount) => {
    const apply = (rows = []) => rows.map((item) => (
      Number(item.id) === Number(postId)
        ? { ...item, isLiked: liked, likeCount: nextLikeCount }
        : item
    ))
    setPosts((prev) => apply(prev))
    setFavorites((prev) => apply(prev))
    setLikes((prev) => apply(prev))
    setUserPosts((prev) => apply(prev))
    setSelectedPost((prev) => (prev && Number(prev.id) === Number(postId)
      ? { ...prev, isLiked: liked, likeCount: nextLikeCount }
      : prev))
  }

  const handleFavoriteChange = (postId, favorited, nextFavoriteCount) => {
    const apply = (rows = []) => rows.map((item) => (
      Number(item.id) === Number(postId)
        ? { ...item, isFavorited: favorited, favoriteCount: nextFavoriteCount }
        : item
    ))
    setPosts((prev) => apply(prev))
    setFavorites((prev) => apply(prev))
    setLikes((prev) => apply(prev))
    setUserPosts((prev) => apply(prev))
    setSelectedPost((prev) => (prev && Number(prev.id) === Number(postId)
      ? { ...prev, isFavorited: favorited, favoriteCount: nextFavoriteCount }
      : prev))
  }

  const displayLabels = useMemo(() => {
    return [...labels].sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')))
  }, [labels])

  const displayPosts = useMemo(() => {
    if (viewMode !== 'all') {
      if (favoritesTab === 'likes') return likes
      if (favoritesTab === 'posts') return userPosts
      return favorites
    }
    return posts
  }, [posts, query, selectedTags, sort, viewMode, error, favorites, likes, userPosts, favoritesTab])

  const handleBackToList = () => {
    setSelectedPost(null)
    const params = new URLSearchParams(window.location.search)
    params.delete('postId')
    const queryString = params.toString()
    window.history.replaceState({}, '', `${window.location.pathname}${queryString ? `?${queryString}` : ''}`)
  }

  const handleBackToHub = () => {
    const params = new URLSearchParams(window.location.search)
    const ui = params.get('ui')
    const target = ui ? `/forum-gate.html?ui=${encodeURIComponent(ui)}` : '/forum-gate.html'
    window.location.href = target
  }

  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    const postId = Number(params.get('postId') || 0)
    if (!postId) return

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
          <PostDetail
            post={selectedPost}
            onBack={handleBackToList}
            onAddComment={handleAddComment}
            onLikeChange={handleLikeChange}
            onFavoriteChange={handleFavoriteChange}
            labelOptions={displayLabels}
            currentUser={currentUser}
          />
        </div>
      ) : (
        <div className={`v2-stage${viewMode === 'favorites' ? ' v2-stage--mine' : ''}`}>
          {viewMode === 'all' ? (
            <button
              type="button"
              className="v2-back-btn v2-back-btn--global"
              aria-label="Back to forum hub"
              onClick={handleBackToHub}
            >
              <span className="v2-back-btn__icon" />
            </button>
          ) : null}

          {viewMode === 'favorites' ? (
            <>
              <button
                type="button"
                className="v2-back-btn v2-back-btn--global v2-back-btn--favorites"
                aria-label="Back to forum"
                onClick={() => setViewMode('all')}
              >
                <span className="v2-back-btn__icon v2-back-btn__icon--favorites" />
              </button>
              <div className="v2-favorites-tabs v2-favorites-tabs--overlay" role="tablist" aria-label="Favorites tabs">
                <button
                  type="button"
                  role="tab"
                  aria-label="Favorites"
                  aria-selected={favoritesTab === 'favorites'}
                  className={`v2-favorites-tab v2-favorites-tab--ghost v2-favorites-tab--fav ${favoritesTab === 'favorites' ? 'is-active' : ''}`}
                  onClick={() => setFavoritesTab('favorites')}
                >
                  <span className="v2-sr-only">Favorites</span>
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-label="Liked"
                  aria-selected={favoritesTab === 'likes'}
                  className={`v2-favorites-tab v2-favorites-tab--ghost v2-favorites-tab--like ${favoritesTab === 'likes' ? 'is-active' : ''}`}
                  onClick={() => setFavoritesTab('likes')}
                >
                  <span className="v2-sr-only">Liked</span>
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-label="My Posts"
                  aria-selected={favoritesTab === 'posts'}
                  className={`v2-favorites-tab v2-favorites-tab--ghost v2-favorites-tab--my ${favoritesTab === 'posts' ? 'is-active' : ''}`}
                  onClick={() => setFavoritesTab('posts')}
                >
                  <span className="v2-sr-only">My Posts</span>
                </button>
              </div>
            </>
          ) : null}

          {viewMode !== 'favorites' ? (
            <aside className="v2-label-zone">
            <div className="v2-label-panel">
              <div className="v2-label-head">
                <h3>Labels</h3>
                <button
                  type="button"
                  className="v2-filter-clear"
                  onClick={() => setSelectedTags([])}
                >
                  Clear
                </button>
              </div>
              <div className="v2-filter-list" role="group" aria-label="Label filters">
                {displayLabels.map((label) => (
                  <label key={label.id} className="v2-filter-item">
                    <input
                      type="checkbox"
                      checked={selectedTags.includes(label.name)}
                      onChange={() => toggleTag(label.name)}
                    />
                    <span>{label.name}</span>
                  </label>
                ))}
              </div>
            </div>
            </aside>
          ) : null}

          <section className={`v2-content-zone${viewMode === 'favorites' ? ' v2-content-zone--favorites' : ''}`}>
            <div className={`v2-board${viewMode === 'favorites' ? ' v2-board--favorites' : ''}`}>
              <section className="v2-main-column">
                {viewMode !== 'favorites' ? (
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
                ) : null}

                <div className="v2-feed-shell">
                  {loading ? <div className="v2-state">Loading...</div> : null}
                  {error ? <div className="v2-state v2-state--error">{error}</div> : null}

                  {!loading ? (
                    <div className="v2-feed-scroll">
                      {displayPosts.map((post) => (
                        <article
                          className="v2-card"
                          key={post.id}
                          onClick={() => openPost(post)}
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
                      {displayPosts.length === 0 ? <div className="v2-state v2-state--empty">No posts found.</div> : null}
                    </div>
                  ) : null}
                </div>
              </section>
            </div>
          </section>

          {viewMode !== 'favorites' ? (
            <div className="v2-fab-stack">
              <button className="v2-fab v2-fab--post" aria-label="Create Post" onClick={() => setShowComposer(true)} />
              <button
                aria-label="Open Favorites"
                className={`v2-fab v2-fab--mine ${viewMode === 'favorites' ? 'v2-fab--active' : ''}`}
                onClick={() => {
                  setFavoritesTab('favorites')
                  setViewMode((prev) => (prev === 'favorites' ? 'all' : 'favorites'))
                }}
              />
            </div>
          ) : null}
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
