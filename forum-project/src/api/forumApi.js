const API_BASE = 'http://127.0.0.1:8001/forum-project/api';
const REALTIME_WS_URL = 'ws://127.0.0.1:3001/ws'

async function forumFetch(path, options = {}) {
  const response = await fetch(`${API_BASE}${path}`, {
    method: options.method || 'GET',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    credentials: 'include',
    body: options.body || undefined,
  });

  let data;
  try {
    data = await response.json();
  } catch (e) {
    data = {
      ok: false,
      message: 'Invalid server response.',
    };
  }

  if (!response.ok) {
    if (response.status === 401) {
      throw new Error('Login required.');
    }
    throw new Error(data.message || 'Request failed.');
  }

  if (data.ok === false) {
    throw new Error(data.message || 'Request failed.');
  }

  return data;
}

export async function fetchLabels() {
  return forumFetch('/labels.php');
}

export async function fetchPosts(params = {}) {
  const qs = new URLSearchParams();
  if (params.q) qs.set('q', params.q);
  if (Array.isArray(params.labels) && params.labels.length > 0) qs.set('labels', params.labels.join(','));
  if (params.sort) qs.set('sort', params.sort);
  const query = qs.toString();
  return forumFetch(`/posts.php${query ? `?${query}` : ''}`);
}

export async function fetchPostDetail(postId) {
  return forumFetch(`/post.php?id=${encodeURIComponent(postId)}`);
}

export async function createPost(payload) {
  return forumFetch('/posts.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function incrementPostViews(postId) {
  return forumFetch('/view.php', {
    method: 'POST',
    body: JSON.stringify({ postId }),
  });
}

export async function createComment(payload) {
  return forumFetch('/comments.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function deletePost(postId) {
  return forumFetch('/delete-post.php', {
    method: 'POST',
    body: JSON.stringify({ postId }),
  });
}

export async function deleteComment(commentId) {
  return forumFetch('/delete-comment.php', {
    method: 'POST',
    body: JSON.stringify({ commentId }),
  });
}

export async function likePost(postId) {
  return forumFetch('/like-post.php', {
    method: 'POST',
    body: JSON.stringify({ postId }),
  });
}

export async function favoritePost(postId) {
  return forumFetch('/favorite-post.php', {
    method: 'POST',
    body: JSON.stringify({ postId }),
  });
}

export async function fetchUserLikes() {
  return forumFetch('/user-likes.php');
}

export async function fetchUserFavorites() {
  return forumFetch('/user-favorites.php');
}

export async function fetchUserPosts() {
  return forumFetch('/user-posts.php');
}

export async function uploadForumAsset(file, kind = 'image') {
  const formData = new FormData();
  formData.append('file', file);
  formData.append('kind', kind);

  const response = await fetch(`${API_BASE}/upload.php`, {
    method: 'POST',
    credentials: 'include',
    body: formData,
  });

  const data = await response.json().catch(() => ({
    ok: false,
    message: 'Invalid server response.',
  }));

  if (!response.ok || data.ok === false) {
    throw new Error(data.message || 'Upload failed.');
  }

  return data;
}

export async function fetchChatConversations() {
  return forumFetch('/chat-conversations.php');
}

export async function searchChatUsers(query = '') {
  return forumFetch(`/chat-users.php${query ? `?q=${encodeURIComponent(query)}` : ''}`);
}

export async function createDirectConversation(targetUserId) {
  return forumFetch('/chat-conversations.php', {
    method: 'POST',
    body: JSON.stringify({
      action: 'direct',
      targetUserId,
    }),
  });
}

export async function createGroupConversation({ memberIds = [], conversationId = null, title = '' }) {
  return forumFetch('/chat-conversations.php', {
    method: 'POST',
    body: JSON.stringify({
      action: 'group',
      memberIds,
      conversationId,
      title,
    }),
  });
}

export async function renameChatConversation(conversationId, title) {
  return forumFetch('/chat-conversations.php', {
    method: 'POST',
    body: JSON.stringify({
      action: 'rename',
      conversationId,
      title,
    }),
  })
}

export async function deleteChatConversation(conversationId) {
  return forumFetch('/chat-conversations.php', {
    method: 'POST',
    body: JSON.stringify({
      action: 'delete',
      conversationId,
    }),
  })
}

export async function fetchChatMessages(conversationId) {
  return forumFetch(`/chat-messages.php?conversationId=${encodeURIComponent(conversationId)}`);
}

export async function sendChatMessage(conversationId, content) {
  return forumFetch('/chat-messages.php', {
    method: 'POST',
    body: JSON.stringify({
      conversationId,
      content,
    }),
  });
}

export async function fetchSessionUser() {
  const response = await fetch('http://127.0.0.1:8001/Auth/backend/api/me.php', {
    credentials: 'include',
  });

  const data = await response.json().catch(() => ({
    status: 'error',
    message: 'Invalid server response.',
  }));

  if (!response.ok || data.status !== 'success') {
    throw new Error(data.message || 'Login required.');
  }

  return data;
}

export async function fetchMessageCenter(summaryOnly = false) {
  return forumFetch(`/message-center.php${summaryOnly ? '?summaryOnly=1' : ''}`)
}

export async function markMessageCenterCategoryRead(category) {
  return forumFetch('/message-center.php', {
    method: 'POST',
    body: JSON.stringify({ category }),
  })
}

export async function markMessageCenterNoticeRead(noticeId, noticeKind = 'system') {
  return forumFetch('/message-center.php', {
    method: 'POST',
    body: JSON.stringify({ category: 'notice', noticeId, noticeKind }),
  })
}

export function connectRealtime(onEvent, onStatusChange) {
  let socket = null
  let reconnectTimer = null
  let closedManually = false

  const connect = () => {
    if (closedManually) {
      return
    }

    socket = new WebSocket(REALTIME_WS_URL)

    socket.addEventListener('open', () => {
      onStatusChange?.('connected')
    })

    socket.addEventListener('message', (event) => {
      try {
        const payload = JSON.parse(event.data)
        onEvent?.(payload)
      } catch (_err) {
        // Ignore malformed events from the relay.
      }
    })

    socket.addEventListener('close', () => {
      onStatusChange?.('disconnected')
      if (!closedManually) {
        reconnectTimer = window.setTimeout(connect, 1200)
      }
    })

    socket.addEventListener('error', () => {
      onStatusChange?.('error')
      socket?.close()
    })
  }

  connect()

  return () => {
    closedManually = true
    if (reconnectTimer) {
      window.clearTimeout(reconnectTimer)
    }
    if (socket && socket.readyState <= 1) {
      socket.close()
    }
  }
}
