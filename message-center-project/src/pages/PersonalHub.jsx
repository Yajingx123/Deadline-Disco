import { useEffect, useMemo, useRef, useState } from 'react'
import MessageComposer from '../components/MessageComposer'
import {
  connectRealtime,
  createDirectConversation,
  createGroupConversation,
  deleteChatConversation,
  fetchChatConversations,
  fetchMessageCenter,
  fetchChatMessages,
  fetchSessionUser,
  renameChatConversation,
  searchChatUsers,
  sendChatMessage,
} from '../api/forumApi'
import { enhanceRenderedAudioPlayers, getSummary, renderFormattedText } from '../utils/formatText'
import './PersonalHub.css'

function upsertConversation(conversations, nextConversation) {
  const nextId = Number(nextConversation?.id || 0)
  if (!nextId) {
    return conversations
  }
  const filtered = conversations.filter((item) => Number(item.id) !== nextId)
  return [nextConversation, ...filtered]
}

function upsertMessage(messages, nextMessage) {
  const nextId = Number(nextMessage?.id || 0)
  if (!nextId) {
    return messages
  }
  if (messages.some((item) => Number(item.id) === nextId)) {
    return messages
  }
  return [...messages, nextMessage]
}

function normalizeRealtimeMessage(message, currentUser) {
  if (!message) {
    return null
  }
  const currentUserId = Number(currentUser?.user_id || currentUser?.id || 0)
  const authorId = Number(message?.author?.id || 0)
  return {
    ...message,
    author: {
      ...(message.author || {}),
      isSelf: currentUserId > 0 && authorId === currentUserId,
    },
  }
}

function broadcastMessageSummary(summary = {}) {
  window.dispatchEvent(new CustomEvent('acadbeat:message-summary', {
    detail: {
      summary,
      totalUnread: Number(summary.totalUnread || 0),
    },
  }))
}

function isCurrentMember(member, currentUser) {
  if (!member || !currentUser) return false
  const currentUserId = Number(currentUser.user_id || currentUser.id || 0)
  return Number(member.id) === currentUserId || member.username === currentUser.username
}

function getConversationAvatarMembers(conversation, currentUser) {
  const members = (conversation?.members || []).filter((member) => !isCurrentMember(member, currentUser))
  if (conversation?.type === 'group') {
    return ((conversation?.members || []).slice(0, 9)).map((member) => ({
      id: member.id,
      avatar: member.avatar,
      username: member.username,
    }))
  }
  return members.slice(0, 1).map((member) => ({
    id: member.id,
    avatar: member.avatar,
    username: member.username,
  }))
}

function getGroupAvatarLayoutClass(count) {
  if (count <= 2) return 'chat-stage__groupAvatar--pair'
  if (count <= 4) return 'chat-stage__groupAvatar--quad'
  return 'chat-stage__groupAvatar--grid'
}

function renderConversationAvatar(conversation, currentUser, extraClass = '') {
  const avatarMembers = getConversationAvatarMembers(conversation, currentUser)
  if (conversation?.type === 'group') {
    return (
      <span className={`chat-stage__groupAvatar ${getGroupAvatarLayoutClass(avatarMembers.length)} ${extraClass}`.trim()}>
        {avatarMembers.map((member) => (
          <span key={member.id} className="chat-stage__groupAvatarCell" title={member.username}>
            {member.avatar}
          </span>
        ))}
      </span>
    )
  }

  return (
    <span className={`chat-stage__directAvatar ${extraClass}`.trim()}>
      {avatarMembers[0]?.avatar || conversation?.avatar || 'U'}
    </span>
  )
}

export default function PersonalHub({ onBackToChooser, embedded = false }) {
  const [currentUser, setCurrentUser] = useState(null)
  const [conversations, setConversations] = useState([])
  const [activeConversationId, setActiveConversationId] = useState(null)
  const [activeConversation, setActiveConversation] = useState(null)
  const [messages, setMessages] = useState([])
  const [searchQuery, setSearchQuery] = useState('')
  const [searchResults, setSearchResults] = useState([])
  const [searchOpen, setSearchOpen] = useState(false)
  const [loading, setLoading] = useState(true)
  const [messageLoading, setMessageLoading] = useState(false)
  const [error, setError] = useState('')
  const [groupModalOpen, setGroupModalOpen] = useState(false)
  const [groupSearch, setGroupSearch] = useState('')
  const [groupResults, setGroupResults] = useState([])
  const [selectedGroupMembers, setSelectedGroupMembers] = useState([])
  const [groupTitle, setGroupTitle] = useState('')
  const [menuState, setMenuState] = useState(null)
  const [profileModalOpen, setProfileModalOpen] = useState(false)
  const [profileTitleDraft, setProfileTitleDraft] = useState('')

  const messageRootRef = useRef(null)
  const messageListRef = useRef(null)

  const syncMessageSummary = async () => {
    try {
      const data = await fetchMessageCenter(true)
      broadcastMessageSummary(data?.summary || {})
    } catch (_err) {
      // Ignore transient summary refresh failures.
    }
  }

  const refreshConversations = async () => {
    const [conversationData, sessionData] = await Promise.all([
      fetchChatConversations(),
      fetchSessionUser(),
    ])

    const nextCurrentUser = sessionData.user || conversationData.currentUser || null
    if ((nextCurrentUser?.role || '').toLowerCase() === 'admin') {
      window.location.replace('http://127.0.0.1:8001/admin_page/dist/index.html')
      return
    }
    setCurrentUser(nextCurrentUser)
    setConversations(conversationData.conversations || [])

    if (!activeConversationId && conversationData.conversations?.length) {
      setActiveConversationId(Number(conversationData.conversations[0].id))
    } else if (activeConversationId) {
      const exists = (conversationData.conversations || []).some((item) => Number(item.id) === Number(activeConversationId))
      if (!exists) {
        setActiveConversationId(conversationData.conversations?.length ? Number(conversationData.conversations[0].id) : null)
      }
    }
  }

  useEffect(() => {
    let cancelled = false
    async function bootstrap() {
      setLoading(true)
      setError('')
      try {
        const [conversationData, sessionData] = await Promise.all([
          fetchChatConversations(),
          fetchSessionUser(),
        ])

        if (cancelled) return
        if (((sessionData.user || conversationData.currentUser || {}).role || '').toLowerCase() === 'admin') {
          window.location.replace('http://127.0.0.1:8001/admin_page/dist/index.html')
          return
        }

        setCurrentUser(sessionData.user || conversationData.currentUser || null)
        setConversations(conversationData.conversations || [])
        if (conversationData.conversations?.length) {
          setActiveConversationId(Number(conversationData.conversations[0].id))
        }
      } catch (err) {
        if ((err.message || '').toLowerCase().includes('login required') || (err.message || '').toLowerCase().includes('not logged in')) {
          window.location.href = 'http://127.0.0.1:8001/home.html?login=1'
          return
        }
        if (!cancelled) {
          setError(err?.message || 'Failed to load personal chat.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    bootstrap()
    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false
    if (!searchQuery.trim()) {
      setSearchResults([])
      setSearchOpen(false)
      return undefined
    }

    const timer = window.setTimeout(async () => {
      try {
        const data = await searchChatUsers(searchQuery.trim())
        if (!cancelled) {
          setSearchResults(data.users || [])
          setSearchOpen(true)
        }
      } catch (_err) {
        if (!cancelled) {
          setSearchResults([])
          setSearchOpen(true)
        }
      }
    }, 180)

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [searchQuery])

  useEffect(() => {
    let cancelled = false
    if (!groupModalOpen) {
      return undefined
    }

    const timer = window.setTimeout(async () => {
      try {
        const data = await searchChatUsers(groupSearch.trim())
        if (!cancelled) {
          setGroupResults(data.users || [])
        }
      } catch (_err) {
        if (!cancelled) {
          setGroupResults([])
        }
      }
    }, 160)

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [groupSearch, groupModalOpen])

  useEffect(() => {
    let cancelled = false

    async function loadMessages({ syncConversations = true } = {}) {
      if (!activeConversationId) {
        setActiveConversation(null)
        setMessages([])
        return
      }

      setMessageLoading(true)
      try {
        const data = await fetchChatMessages(activeConversationId)
        if (cancelled) return
        setActiveConversation(data.conversation || null)
        setMessages((prev) => {
          const nextMessages = data.messages || []
          if (prev.length === nextMessages.length) {
            const sameIds = prev.every((message, index) => Number(message.id) === Number(nextMessages[index]?.id))
            if (sameIds) {
              return prev
            }
          }
          return nextMessages
        })
        if (syncConversations) {
          refreshConversations().catch(() => {})
        }
        syncMessageSummary().catch(() => {})
      } catch (err) {
        if (!cancelled) {
          setError(err?.message || 'Failed to load messages.')
        }
      } finally {
        if (!cancelled) {
          setMessageLoading(false)
        }
      }
    }

    loadMessages()
    return () => {
      cancelled = true
    }
  }, [activeConversationId])

  useEffect(() => {
    if (!activeConversationId) {
      return undefined
    }

    const pollTimer = window.setInterval(() => {
      if (document.hidden) {
        return
      }
      fetchChatMessages(activeConversationId)
        .then((data) => {
          setActiveConversation(data.conversation || null)
          setMessages((prev) => {
            const nextMessages = data.messages || []
            if (prev.length === nextMessages.length) {
              const sameIds = prev.every((message, index) => Number(message.id) === Number(nextMessages[index]?.id))
              if (sameIds) {
                return prev
              }
            }
            return nextMessages
          })
          syncMessageSummary().catch(() => {})
        })
        .catch(() => {})
    }, 2500)

    return () => window.clearInterval(pollTimer)
  }, [activeConversationId])

  useEffect(() => {
    if (loading) {
      return undefined
    }

    return connectRealtime(async (event) => {
      const eventType = String(event?.type || '')
      const eventConversationId = Number(event?.data?.conversationId || 0)
      const eventMessage = normalizeRealtimeMessage(event?.data?.message || null, currentUser)

      if (eventType.startsWith('chat.')) {
        await refreshConversations().catch(() => {})
      }

      if (eventType === 'chat.message.created' || eventType === 'message-center.updated') {
        syncMessageSummary().catch(() => {})
      }

      if (
        activeConversationId
        && eventConversationId
        && Number(activeConversationId) === eventConversationId
        && (eventType === 'chat.message.created' || eventType === 'chat.conversation.created')
      ) {
        if (eventType === 'chat.message.created' && eventMessage) {
          setMessages((prev) => upsertMessage(prev, eventMessage))
          setConversations((prev) => prev.map((conversation) => (
            Number(conversation.id) === Number(eventConversationId)
              ? {
                  ...conversation,
                  lastMessagePreview: getSummary(eventMessage.content || '', 80),
                  lastMessageAuthor: eventMessage.author?.username || conversation.lastMessageAuthor,
                  lastMessageAt: eventMessage.createdAt || new Date().toISOString(),
                }
              : conversation
          )))
        }
        try {
          const data = await fetchChatMessages(activeConversationId)
          setActiveConversation(data.conversation || null)
          setMessages((prev) => {
            const nextMessages = data.messages || []
            if (prev.length === nextMessages.length) {
              const sameIds = prev.every((message, index) => Number(message.id) === Number(nextMessages[index]?.id))
              if (sameIds) {
                return prev
              }
            }
            return nextMessages
          })
        } catch (_err) {
          // Keep current state if realtime hydration fails.
        }
      }
    })
  }, [loading, activeConversationId, currentUser])

  useEffect(() => enhanceRenderedAudioPlayers(messageRootRef.current), [messages])

  useEffect(() => {
    const closeMenu = () => setMenuState(null)
    window.addEventListener('click', closeMenu)
    return () => window.removeEventListener('click', closeMenu)
  }, [])

  useEffect(() => {
    const container = messageListRef.current
    if (!container) return
    const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight
    const isNearBottom = distanceFromBottom < 120
    if (isNearBottom) {
      container.scrollTop = container.scrollHeight
    }
  }, [messages])

  useEffect(() => {
    const container = messageListRef.current
    if (container) {
      container.scrollTop = container.scrollHeight
    }
  }, [activeConversationId])

  const visibleConversations = useMemo(() => conversations, [conversations])
  const activeAvatarMembers = useMemo(
    () => getConversationAvatarMembers(activeConversation, currentUser),
    [activeConversation, currentUser],
  )

  const handleStartDirect = async (user) => {
    try {
      const data = await createDirectConversation(user.id)
      const nextConversation = data.conversation
      setConversations((prev) => upsertConversation(prev, nextConversation))
      setActiveConversationId(Number(nextConversation.id))
      setSearchQuery('')
      setSearchResults([])
      setSearchOpen(false)
    } catch (err) {
      setError(err?.message || 'Failed to start direct chat.')
    }
  }

  const handleSendMessage = async (content) => {
    if (!activeConversationId) {
      throw new Error('Please open a conversation first.')
    }

    const data = await sendChatMessage(activeConversationId, content)
    if (data.message) {
      setMessages((prev) => [...prev, data.message])
    }

    setConversations((prev) => prev.map((conversation) => (
      Number(conversation.id) === Number(activeConversationId)
        ? {
            ...conversation,
            lastMessagePreview: getSummary(content, 80),
            lastMessageAuthor: currentUser?.username || '',
            lastMessageAt: new Date().toISOString(),
          }
        : conversation
    )))

    await refreshConversations()
    await syncMessageSummary()
  }

  const openGroupModal = async () => {
    setGroupModalOpen(true)
    setGroupTitle('')
    setSelectedGroupMembers([])
    setGroupSearch('')
    try {
      const data = await searchChatUsers('')
      setGroupResults(data.users || [])
    } catch (_err) {
      setGroupResults([])
    }
  }

  const handleCreateGroup = async () => {
    if (!activeConversationId) {
      return
    }

    try {
      const isExistingGroup = activeConversation?.type === 'group'
      const data = await createGroupConversation({
        conversationId: activeConversationId,
        memberIds: selectedGroupMembers,
        title: isExistingGroup ? '' : groupTitle.trim(),
      })
      const nextConversation = data.conversation
      setConversations((prev) => upsertConversation(prev, nextConversation))
      setActiveConversationId(Number(nextConversation.id))
      setGroupModalOpen(false)
      setSelectedGroupMembers([])
      setGroupTitle('')
    } catch (err) {
      setError(err?.message || 'Failed to update group chat.')
    }
  }

  const toggleGroupMember = (userId) => {
    setSelectedGroupMembers((prev) => (
      prev.includes(userId)
        ? prev.filter((item) => item !== userId)
        : [...prev, userId]
    ))
  }

  const existingMemberIds = useMemo(
    () => new Set((activeConversation?.members || []).map((member) => Number(member.id))),
    [activeConversation],
  )
  const isExistingGroupChat = activeConversation?.type === 'group'

  const openProfileModal = () => {
    if (!activeConversation || activeConversation.type !== 'group') {
      return
    }
    setProfileTitleDraft(activeConversation.customTitle || activeConversation.title || '')
    setProfileModalOpen(true)
  }

  const handleRenameGroup = async () => {
    if (!activeConversationId || activeConversation?.type !== 'group') {
      return
    }

    try {
      const data = await renameChatConversation(activeConversationId, profileTitleDraft.trim())
      if (data.conversation) {
        setActiveConversation(data.conversation)
        setConversations((prev) => upsertConversation(prev, data.conversation))
      }
      setProfileModalOpen(false)
    } catch (err) {
      setError(err?.message || 'Failed to rename group chat.')
    }
  }

  const handleDeleteConversation = async (conversationId) => {
    try {
      await deleteChatConversation(conversationId)
      setConversations((prev) => prev.filter((conversation) => Number(conversation.id) !== Number(conversationId)))
      if (Number(activeConversationId) === Number(conversationId)) {
        const nextConversation = conversations.find((conversation) => Number(conversation.id) !== Number(conversationId))
        setActiveConversationId(nextConversation ? Number(nextConversation.id) : null)
        if (!nextConversation) {
          setActiveConversation(null)
          setMessages([])
        }
      }
      setMenuState(null)
    } catch (err) {
      setError(err?.message || 'Failed to delete conversation.')
    }
  }

  const workspace = (
      <section className={`personal-shell ${embedded ? 'personal-shell--embedded' : ''}`}>
        {loading ? (
          <div className="personal-state">Loading your workspace…</div>
        ) : error ? (
          <div className="personal-state personal-state--error">{error}</div>
        ) : (
          <div className="personal-layout">
            <aside className="chat-sidebar">
                <div className="chat-sidebar__searchBlock">
                  <div className="chat-sidebar__titleRow">
                    <div className="chat-sidebar__heading">
                      <div className="chat-sidebar__eyebrow">Private Space</div>
                      <h2 className="chat-sidebar__title">Chats</h2>
                    </div>
                    {!embedded && (
                      <button type="button" className="personal-shell__back" onClick={onBackToChooser}>
                        Back
                      </button>
                    )}
                  </div>

                  <div className="chat-sidebar__searchShell">
                    <input
                      className="chat-sidebar__search"
                      type="text"
                      value={searchQuery}
                      onChange={(event) => setSearchQuery(event.target.value)}
                      onFocus={() => {
                        if (searchQuery.trim()) setSearchOpen(true)
                      }}
                      placeholder="Search people by username"
                    />

                    {searchOpen && searchQuery.trim() && (
                      <div className="chat-sidebar__searchResults">
                        {searchResults.map((user) => (
                          <button key={user.id} type="button" className="chat-search-card" onClick={() => handleStartDirect(user)}>
                            <span className="chat-search-card__avatar">{user.avatar}</span>
                            <span className="chat-search-card__meta">
                              <strong>{user.username}</strong>
                              <span>{user.email}</span>
                            </span>
                          </button>
                        ))}
                        {!searchResults.length && (
                          <div className="chat-sidebar__empty">No users found.</div>
                        )}
                      </div>
                    )}
                  </div>
                </div>

              <div className="chat-sidebar__list">
                {visibleConversations.map((conversation) => (
                  <button
                    key={conversation.id}
                    type="button"
                    className={`chat-thread ${Number(activeConversationId) === Number(conversation.id) ? 'is-active' : ''}`}
                    onClick={() => setActiveConversationId(Number(conversation.id))}
                    onContextMenu={(event) => {
                      event.preventDefault()
                      setMenuState({
                        conversationId: Number(conversation.id),
                        x: event.clientX,
                        y: event.clientY,
                      })
                    }}
                  >
                    <span className="chat-thread__avatar">
                      {renderConversationAvatar(conversation, currentUser, 'chat-thread__compositeAvatar')}
                    </span>
                    <span className="chat-thread__body">
                      <span className="chat-thread__top">
                        <strong>{conversation.title}</strong>
                        <span>{conversation.memberCount} members</span>
                      </span>
                      <span className="chat-thread__preview">{conversation.lastMessagePreview || 'No messages yet.'}</span>
                    </span>
                    {Number(conversation.unreadCount || 0) > 0 && (
                      <span className="chat-thread__unread">
                        {Number(conversation.unreadCount) > 99 ? '99+' : Number(conversation.unreadCount)}
                      </span>
                    )}
                  </button>
                ))}

                {!visibleConversations.length && (
                  <div className="chat-sidebar__empty">No conversation history yet.</div>
                )}
              </div>
            </aside>

            <main className="chat-stage">
              {!activeConversation ? (
                <div className="chat-stage__empty">Select a conversation or search a username to start a new chat.</div>
              ) : (
                <>
                  <div className="chat-stage__header">
                    <div>
                      <div className="chat-stage__type">{activeConversation.type === 'group' ? 'Group Chat' : 'Direct Message'}</div>
                      <h2 className="chat-stage__title">{activeConversation.title}</h2>
                      <div className="chat-stage__members">
                        {(activeConversation.members || []).map((member) => (
                          <span key={member.id} className="chat-stage__member">
                            {member.username}{isCurrentMember(member, currentUser) ? ' (You)' : ''}
                          </span>
                        ))}
                      </div>
                    </div>

                    <div className="chat-stage__actions">
                      <button
                        type="button"
                        className={`chat-stage__identity ${activeConversation.type === 'group' ? 'is-group' : ''}`}
                        onClick={activeConversation.type === 'group' ? openProfileModal : undefined}
                        aria-label={activeConversation.type === 'group' ? 'Open group details' : 'Direct chat avatar'}
                      >
                        {renderConversationAvatar(activeConversation, currentUser)}
                      </button>

                      <button type="button" className="chat-stage__addBtn" onClick={openGroupModal}>
                        Add Members
                      </button>
                    </div>
                  </div>

                  <div className="chat-stage__messages" ref={messageRootRef}>
                    <div className="chat-stage__messagesInner" ref={messageListRef}>
                      {messageLoading ? (
                        <div className="chat-stage__status">Loading conversation…</div>
                      ) : messages.length === 0 ? null : (
                        messages.map((message) => (
                          <div key={message.id} className={`chat-message ${message.author?.isSelf ? 'is-self' : ''}`}>
                            <div className="chat-message__avatar">{message.author?.avatar}</div>
                            <div className="chat-message__bubble">
                              <div className="chat-message__meta">
                                <span>{message.author?.username}</span>
                                <span>{message.displayTime}</span>
                              </div>
                              <div
                                className="chat-message__content"
                                dangerouslySetInnerHTML={{ __html: renderFormattedText(message.content) }}
                              />
                            </div>
                          </div>
                        ))
                      )}
                    </div>
                  </div>

                  <MessageComposer disabled={!activeConversationId} onSend={handleSendMessage} />
                </>
              )}
            </main>
          </div>
        )}
      </section>
  )

  return (
    <div className={`forum-container forum-container--personal ${embedded ? 'forum-container--embedded' : ''}`}>
      {workspace}

      {groupModalOpen && (
        <div className="personal-modal">
          <div className="personal-modal__backdrop" onClick={() => setGroupModalOpen(false)} />
            <div className="personal-modal__card">
            <div className="personal-modal__header">
              <h3>{isExistingGroupChat ? 'Add Members To Group' : 'Start Group Chat'}</h3>
              <button type="button" className="personal-modal__close" onClick={() => setGroupModalOpen(false)}>✕</button>
            </div>

            {!isExistingGroupChat && (
              <input
                className="personal-modal__input"
                type="text"
                value={groupTitle}
                onChange={(event) => setGroupTitle(event.target.value)}
                placeholder="Optional group title"
              />
            )}

            <input
              className="personal-modal__input"
              type="text"
              value={groupSearch}
              onChange={(event) => setGroupSearch(event.target.value)}
              placeholder="Search people to add"
            />

            <div className="personal-modal__list">
              {groupResults
                .filter((user) => !existingMemberIds.has(Number(user.id)))
                .map((user) => (
                  <label key={user.id} className="personal-modal__user">
                    <input
                      type="checkbox"
                      checked={selectedGroupMembers.includes(user.id)}
                      onChange={() => toggleGroupMember(user.id)}
                    />
                    <span className="personal-modal__avatar">{user.avatar}</span>
                    <span>
                      <strong>{user.username}</strong>
                      <small>{user.email}</small>
                    </span>
                  </label>
                ))}
              {!groupResults.filter((user) => !existingMemberIds.has(Number(user.id))).length && (
                <div className="chat-sidebar__empty">No extra members available.</div>
              )}
            </div>

            <div className="personal-modal__footer">
              <button type="button" className="personal-modal__ghost" onClick={() => setGroupModalOpen(false)}>Cancel</button>
              <button type="button" className="personal-modal__primary" onClick={handleCreateGroup} disabled={!selectedGroupMembers.length}>
                {isExistingGroupChat ? 'Add members' : 'Create group'}
              </button>
            </div>
          </div>
        </div>
      )}

      {menuState && (
        <div
          className="chat-thread-menu"
          style={{ left: `${menuState.x}px`, top: `${menuState.y}px` }}
          onClick={(event) => event.stopPropagation()}
        >
          <button
            type="button"
            className="chat-thread-menu__item chat-thread-menu__item--danger"
            onClick={() => handleDeleteConversation(menuState.conversationId)}
          >
            Delete Chat
          </button>
        </div>
      )}

      {profileModalOpen && activeConversation?.type === 'group' && (
        <div className="personal-modal">
          <div className="personal-modal__backdrop" onClick={() => setProfileModalOpen(false)} />
          <div className="personal-modal__card personal-modal__card--profile">
            <div className="personal-modal__header">
              <h3>Group Details</h3>
              <button type="button" className="personal-modal__close" onClick={() => setProfileModalOpen(false)}>✕</button>
            </div>

            <div className="group-profile__hero">
              {renderConversationAvatar(activeConversation, currentUser, 'chat-stage__groupAvatar--large')}
              <div className="group-profile__meta">
                <strong>{activeConversation.title}</strong>
                <span>{activeConversation.memberCount} members</span>
              </div>
            </div>

            <div className="group-profile__field">
              <label htmlFor="group-title-input">Group title</label>
              <input
                id="group-title-input"
                className="personal-modal__input"
                type="text"
                value={profileTitleDraft}
                onChange={(event) => setProfileTitleDraft(event.target.value)}
                placeholder="Enter a group title"
              />
            </div>

            <div className="group-profile__members">
              <div className="group-profile__sectionTitle">Members</div>
              <div className="personal-modal__list">
                {(activeConversation.members || []).map((member) => (
                  <div key={member.id} className="personal-modal__user">
                    <span className="personal-modal__avatar">{member.avatar}</span>
                    <span>
                      <strong>{member.username}{isCurrentMember(member, currentUser) ? ' (You)' : ''}</strong>
                      <small>{member.email}</small>
                    </span>
                  </div>
                ))}
              </div>
            </div>

            <div className="personal-modal__footer">
              <button type="button" className="personal-modal__ghost" onClick={() => setProfileModalOpen(false)}>Close</button>
              <button type="button" className="personal-modal__primary" onClick={handleRenameGroup}>
                Save Title
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
