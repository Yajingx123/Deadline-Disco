const API_BASE = 'http://127.0.0.1:8001/forum-project/api';

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

  const data = await response.json().catch(() => ({
    ok: false,
    message: 'Invalid server response.',
  }));

  if (!response.ok || data.ok === false) {
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
