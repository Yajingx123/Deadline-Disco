import { useEffect, useMemo, useRef, useState } from 'react'
import MessageComposer from '../components/MessageComposer'
import DrawGuessPanel from '../components/DrawGuessPanel'
import {
  connectRealtime,
  createDirectConversation,
  createGroupConversation,
  deleteChatConversation,
  fetchDrawGuessGame,
  fetchChatConversations,
  fetchMessageCenter,
  fetchChatMessages,
  fetchSessionUser,
  mutateDrawGuessGame,
  renameChatConversation,
  searchChatUsers,
  sendChatMessage,
} from '../api/forumApi'
import { enhanceRenderedAudioPlayers, getSummary, renderFormattedText } from '../utils/formatText'
import './PersonalHub.css'

const MAIN_ORIGIN =
  (typeof window !== 'undefined' && window.ACADBEAT_LOCAL && window.ACADBEAT_LOCAL.mainOrigin)
  || (typeof window !== 'undefined' ? window.location.origin : 'http://127.0.0.1:8001')

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

const DRAW_GUESS_STATUS_RANK = {
  IDLE: 0,
  LOBBY: 1,
  ROUND_START: 2,
  PLAYING: 3,
  ROUND_END: 4,
  GAME_END: 5,
}

function drawGuessStatusRank(status) {
  return DRAW_GUESS_STATUS_RANK[String(status || 'IDLE')] || 0
}

function shouldAcceptDrawGuessState(prev, next) {
  if (!next) return false
  if (!prev) return true

  const prevRoomId = Number(prev.roomId || 0)
  const nextRoomId = Number(next.roomId || 0)
  if (prevRoomId && nextRoomId && nextRoomId !== prevRoomId) {
    return nextRoomId > prevRoomId
  }

  const prevRound = Number(prev.roundIndex || 0)
  const nextRound = Number(next.roundIndex || 0)
  if (nextRound !== prevRound) {
    return nextRound > prevRound
  }

  const prevStatusRank = drawGuessStatusRank(prev.status)
  const nextStatusRank = drawGuessStatusRank(next.status)
  if (nextStatusRank !== prevStatusRank) {
    return nextStatusRank > prevStatusRank
  }

  const prevStrokeCount = Array.isArray(prev.strokes) ? prev.strokes.length : 0
  const nextStrokeCount = Array.isArray(next.strokes) ? next.strokes.length : 0
  if (nextStrokeCount !== prevStrokeCount) {
    return nextStrokeCount > prevStrokeCount
  }

  const prevGuessCount = Array.isArray(prev.recentGuesses) ? prev.recentGuesses.length : 0
  const nextGuessCount = Array.isArray(next.recentGuesses) ? next.recentGuesses.length : 0
  if (nextGuessCount !== prevGuessCount) {
    return nextGuessCount > prevGuessCount
  }

  return true
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
  const [imageViewer, setImageViewer] = useState(null)
  const [drawGuessGame, setDrawGuessGame] = useState(null)
  const [drawGuessError, setDrawGuessError] = useState('')
  const [drawGuessLoading, setDrawGuessLoading] = useState(false)
  const [gameStartModalOpen, setGameStartModalOpen] = useState(false)
  const [gamePlayerCount, setGamePlayerCount] = useState(2)
  const [drawGuessRoomOpen, setDrawGuessRoomOpen] = useState(false)
  const [incomingGamePrompt, setIncomingGamePrompt] = useState(null)

  const messageRootRef = useRef(null)
  const messageListRef = useRef(null)
  const lastDrawGuessRealtimeAtRef = useRef(0)
  const lastDrawGuessPublishedAtRef = useRef(0)
  const drawGuessTickInFlightRef = useRef(false)

  const applyDrawGuessState = (nextGame, source = 'http') => {
    if (!nextGame) {
      setDrawGuessGame(null)
      return
    }
    if (source === 'ws') {
      lastDrawGuessRealtimeAtRef.current = Date.now()
    }
    setDrawGuessGame((prev) => (shouldAcceptDrawGuessState(prev, nextGame) ? nextGame : prev))
    setDrawGuessError('')
  }

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
      window.location.replace(`${MAIN_ORIGIN}/admin_page/dist/index.html`)
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
          window.location.replace(`${MAIN_ORIGIN}/admin_page/dist/index.html`)
          return
        }

        setCurrentUser(sessionData.user || conversationData.currentUser || null)
        setConversations(conversationData.conversations || [])
        if (conversationData.conversations?.length) {
          setActiveConversationId(Number(conversationData.conversations[0].id))
        }
      } catch (err) {
        if ((err.message || '').toLowerCase().includes('login required') || (err.message || '').toLowerCase().includes('not logged in')) {
          window.location.href = `${MAIN_ORIGIN}/home.html?login=1`
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
    let cancelled = false

    async function loadGame() {
      if (!activeConversationId) {
        lastDrawGuessRealtimeAtRef.current = 0
        lastDrawGuessPublishedAtRef.current = 0
        setDrawGuessGame(null)
        setDrawGuessRoomOpen(false)
        return
      }
      lastDrawGuessRealtimeAtRef.current = 0
      lastDrawGuessPublishedAtRef.current = 0
      setDrawGuessLoading(true)
      try {
        const data = await fetchDrawGuessGame(activeConversationId)
        if (!cancelled) {
          if (data.game) {
            applyDrawGuessState(data.game, 'http')
          } else {
            setDrawGuessGame(null)
            setDrawGuessError('')
          }
        }
      } catch (err) {
        if (!cancelled) {
          setDrawGuessGame(null)
          setDrawGuessError(err?.message || 'Failed to load Draw & Guess.')
        }
      } finally {
        if (!cancelled) {
          setDrawGuessLoading(false)
        }
      }
    }

    loadGame()
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
      const currentUserId = Number(currentUser?.user_id || currentUser?.id || 0)

      if (eventType.startsWith('chat.')) {
        await refreshConversations().catch(() => {})
      }

      if (
        eventType.startsWith('game.')
        && activeConversationId
        && eventConversationId
        && Number(activeConversationId) === eventConversationId
      ) {
        const publishedAtMs = Number(event?.data?.publishedAtMs || 0)
        if (publishedAtMs > 0 && publishedAtMs < lastDrawGuessPublishedAtRef.current) {
          return
        }
        const pushedStates = event?.data?.states && typeof event.data.states === 'object' ? event.data.states : null
        const pushedForViewer = pushedStates ? (pushedStates[String(currentUserId)] || pushedStates[currentUserId] || null) : null

        if (eventType === 'game.stroke.broadcast') {
          lastDrawGuessRealtimeAtRef.current = Date.now()
          setDrawGuessGame((prev) => {
            if (!prev || Number(prev.roomId || 0) !== Number(event?.data?.gameId || 0)) {
              return prev
            }
            return {
              ...prev,
              strokes: [...(prev.strokes || []), event?.data?.stroke].filter(Boolean),
            }
          })
        } else if (eventType === 'game.canvas.cleared') {
          lastDrawGuessRealtimeAtRef.current = Date.now()
          setDrawGuessGame((prev) => {
            if (!prev || Number(prev.roomId || 0) !== Number(event?.data?.gameId || 0)) {
              return prev
            }
            return {
              ...prev,
              strokes: [],
            }
          })
        } else {
          if (pushedForViewer) {
            if (publishedAtMs > 0) {
              lastDrawGuessPublishedAtRef.current = Math.max(lastDrawGuessPublishedAtRef.current, publishedAtMs)
            }
            applyDrawGuessState(pushedForViewer, 'ws')
            if (
              pushedForViewer.status !== 'GAME_END'
              && !drawGuessRoomOpen
              && ['game.lobby.updated', 'game.round.started', 'game.state.sync'].includes(eventType)
            ) {
              const drawer = (pushedForViewer.players || []).find((player) => Number(player.userId) === Number(pushedForViewer.currentDrawerId || 0))
              setIncomingGamePrompt({
                title: eventType === 'game.lobby.updated' ? 'Draw & Guess invitation' : 'Draw & Guess is live',
                description: eventType === 'game.lobby.updated'
                  ? `${drawer?.displayName || drawer?.username || 'Someone'} started a game in this chat.`
                  : 'The game state changed. Open the game room to continue.',
              })
            }
          } else {
            if (publishedAtMs > 0) {
              lastDrawGuessPublishedAtRef.current = Math.max(lastDrawGuessPublishedAtRef.current, publishedAtMs)
            }
            try {
              const data = await fetchDrawGuessGame(activeConversationId)
              if (data.game) {
                applyDrawGuessState(data.game, 'http')
              }
            } catch (_err) {
              // Keep current state if game hydration fails.
            }
          }

          if (eventType === 'game.cancelled') {
            const leftByName = String(event?.data?.leftByName || 'A player')
            const serverMessage = String(event?.data?.message || `${leftByName} left the game. This round is closed for everyone.`)
            setIncomingGamePrompt({
              title: 'Draw & Guess ended',
              description: serverMessage,
            })
          }
        }
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
  }, [loading, activeConversationId, currentUser, drawGuessRoomOpen])

  useEffect(() => {
    if (!activeConversationId || !drawGuessGame) {
      return undefined
    }

    const syncableStatuses = ['LOBBY', 'ROUND_START', 'PLAYING', 'ROUND_END']
    const isSyncable = syncableStatuses.includes(String(drawGuessGame.status || ''))
    if (!isSyncable) {
      return undefined
    }

    const runTick = () => {
      if (drawGuessTickInFlightRef.current) {
        return
      }
      drawGuessTickInFlightRef.current = true
      const startedAt = Date.now()
      mutateDrawGuessGame(activeConversationId, 'tick')
        .then((data) => {
          if (!data?.game) return
          if (lastDrawGuessRealtimeAtRef.current > startedAt) {
            return
          }
          applyDrawGuessState(data.game, 'http')
        })
        .catch(() => {})
        .finally(() => {
          drawGuessTickInFlightRef.current = false
        })
    }

    runTick()
    const timer = window.setInterval(runTick, 450)
    return () => window.clearInterval(timer)
  }, [
    activeConversationId,
    drawGuessGame?.roomId,
    drawGuessGame?.status,
  ])

  useEffect(() => {
    if (
      drawGuessRoomOpen
      && drawGuessGame
      && drawGuessGame.status === 'GAME_END'
      && drawGuessGame.endReason === 'cancelled'
    ) {
      setDrawGuessRoomOpen(false)
    }
  }, [drawGuessRoomOpen, drawGuessGame])

  const runDrawGuessAction = async (action, payload = {}) => {
    if (!activeConversationId) return
    const startedAt = Date.now()
    try {
      const data = await mutateDrawGuessGame(activeConversationId, action, payload)
      if (data?.game && lastDrawGuessRealtimeAtRef.current <= startedAt) {
        applyDrawGuessState(data.game, 'http')
      } else {
        setDrawGuessError('')
      }
      if (action === 'leave' && data.game?.status === 'GAME_END') {
        setDrawGuessRoomOpen(false)
      }
    } catch (err) {
      setDrawGuessError(err?.message || 'Draw & Guess request failed.')
    }
  }

  const openGameRoom = async () => {
    if (!activeConversationId) {
      return
    }

    if (drawGuessGame && drawGuessGame.status !== 'GAME_END') {
      setDrawGuessRoomOpen(true)
      setIncomingGamePrompt(null)
      return
    }

    if (drawGuessLoading) {
      return
    }

    const conversationIdAtClick = Number(activeConversationId)
    setDrawGuessLoading(true)
    try {
      const data = await fetchDrawGuessGame(conversationIdAtClick)
      if (Number(activeConversationId) !== conversationIdAtClick) {
        return
      }
      const latestGame = data?.game || null
      if (latestGame && latestGame.status !== 'GAME_END') {
        applyDrawGuessState(latestGame, 'http')
        setDrawGuessRoomOpen(true)
        setIncomingGamePrompt(null)
        return
      }
      openGameStartModal()
    } catch (err) {
      if (Number(activeConversationId) === conversationIdAtClick) {
        setDrawGuessError(err?.message || 'Failed to load Draw & Guess.')
      }
      openGameStartModal()
    } finally {
      if (Number(activeConversationId) === conversationIdAtClick) {
        setDrawGuessLoading(false)
      }
    }
  }

  const openGameStartModal = () => {
    const memberCount = Math.max(2, Math.min(Number(activeConversation?.memberCount || 2), 6))
    setGamePlayerCount(memberCount >= 3 ? 3 : memberCount)
    setGameStartModalOpen(true)
  }

  const closeGameRoom = () => {
    setDrawGuessRoomOpen(false)
  }

  useEffect(() => enhanceRenderedAudioPlayers(messageRootRef.current), [messages])

  useEffect(() => {
    const root = messageRootRef.current
    if (!root) {
      return undefined
    }

    const handleImageClick = (event) => {
      const trigger = event.target.closest('.forumInlineImage')
      if (!trigger) {
        return
      }
      event.preventDefault()
      const image = trigger.querySelector('img')
      setImageViewer({
        src: trigger.getAttribute('href') || image?.getAttribute('src') || '',
        alt: image?.getAttribute('alt') || '',
      })
    }

    root.addEventListener('click', handleImageClick)
    return () => root.removeEventListener('click', handleImageClick)
  }, [messages])

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
          <div className={`personal-layout ${drawGuessRoomOpen ? 'personal-layout--gameMode' : ''}`}>
            {!drawGuessRoomOpen && (
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
            )}

            <main className={`chat-stage ${drawGuessRoomOpen ? 'chat-stage--gameOnly' : ''}`}>
              {!activeConversation ? (
                <div className="chat-stage__empty">Select a conversation or search a username to start a new chat.</div>
              ) : (
                <>
                  {!drawGuessRoomOpen && (
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
                      <button
                        type="button"
                        className="chat-stage__addBtn chat-stage__addBtn--game"
                        onClick={openGameRoom}
                        disabled={drawGuessLoading}
                      >
                        {drawGuessLoading ? 'Checking...' : (drawGuessGame && drawGuessGame.status !== 'GAME_END') ? 'Open Game' : 'Start Game'}
                      </button>
                    </div>
                  </div>
                  )}

                  {drawGuessRoomOpen && (drawGuessGame || drawGuessLoading || drawGuessError) && (
                    <div className="chat-stage__gameRoom">
                      {drawGuessError ? (
                        <div className="chat-stage__gameError">{drawGuessError}</div>
                      ) : drawGuessLoading ? (
                        <div className="chat-stage__gameLoading">Loading Draw & Guess…</div>
                      ) : (
                        <DrawGuessPanel
                          game={drawGuessGame}
                          currentUser={currentUser}
                          onCreateLobby={() => runDrawGuessAction('createLobby')}
                          onToggleReady={(isReady) => runDrawGuessAction('toggleReady', { isReady })}
                          onStartGame={() => runDrawGuessAction('startGame')}
                          onPickWord={(wordId) => runDrawGuessAction('pickWord', { wordId })}
                          onSubmitGuess={(guess) => runDrawGuessAction('submitGuess', { guess })}
                          onStroke={(payload) => runDrawGuessAction('stroke', { payload })}
                          onClearCanvas={() => runDrawGuessAction('clearCanvas')}
                          onLeaveGame={() => runDrawGuessAction('leave')}
                          onExitGameView={closeGameRoom}
                        />
                      )}
                    </div>
                  )}

                  <div className={`chat-stage__messages ${drawGuessRoomOpen ? 'is-hiddenForGame' : ''}`} ref={messageRootRef}>
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

                  <div className={drawGuessRoomOpen ? 'chat-stage__composerWrap is-hiddenForGame' : 'chat-stage__composerWrap'}>
                    <MessageComposer disabled={!activeConversationId} onSend={handleSendMessage} />
                  </div>
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

      {gameStartModalOpen && (
        <div className="personal-modal">
          <div className="personal-modal__backdrop" onClick={() => setGameStartModalOpen(false)} />
          <div className="personal-modal__card personal-modal__card--compact">
            <div className="personal-modal__header">
              <h3>Start Draw & Guess</h3>
              <button type="button" className="personal-modal__close" onClick={() => setGameStartModalOpen(false)}>✕</button>
            </div>
            <div className="personal-modal__body">
              <p className="personal-modal__hint">
                Choose how many people should join this round. After confirmation, an invitation will be posted automatically in this chat.
              </p>
              <label className="personal-modal__label">
                Players for this round
                <select
                  className="personal-modal__input"
                  value={gamePlayerCount}
                  onChange={(event) => setGamePlayerCount(Number(event.target.value))}
                >
                  {Array.from({ length: Math.max(1, Math.min(Number(activeConversation?.memberCount || 2), 6)) - 1 }, (_, index) => index + 2).map((count) => (
                    <option key={count} value={count}>{count}</option>
                  ))}
                </select>
              </label>
            </div>
            <div className="personal-modal__footer">
              <button type="button" className="personal-modal__secondary" onClick={() => setGameStartModalOpen(false)}>Cancel</button>
              <button
                type="button"
                className="personal-modal__primary"
                onClick={async () => {
                  await runDrawGuessAction('createLobby', { minPlayers: gamePlayerCount })
                  setGameStartModalOpen(false)
                  setDrawGuessRoomOpen(true)
                }}
              >
                Confirm
              </button>
            </div>
          </div>
        </div>
      )}

      {incomingGamePrompt && (
        <div className="personal-modal">
          <div className="personal-modal__backdrop" onClick={() => setIncomingGamePrompt(null)} />
          <div className="personal-modal__card personal-modal__card--compact">
            <div className="personal-modal__header">
              <h3>{incomingGamePrompt.title}</h3>
              <button type="button" className="personal-modal__close" onClick={() => setIncomingGamePrompt(null)}>✕</button>
            </div>
            <div className="personal-modal__body">
              <p className="personal-modal__hint">{incomingGamePrompt.description}</p>
            </div>
            <div className="personal-modal__footer">
              <button type="button" className="personal-modal__secondary" onClick={() => setIncomingGamePrompt(null)}>Later</button>
              <button
                type="button"
                className="personal-modal__primary"
                onClick={() => {
                  setIncomingGamePrompt(null)
                  setDrawGuessRoomOpen(true)
                }}
              >
                Open Game
              </button>
            </div>
          </div>
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

      {imageViewer?.src && (
        <div className="chat-image-viewer" onClick={() => setImageViewer(null)}>
          <button type="button" className="chat-image-viewer__back" onClick={() => setImageViewer(null)}>
            Back
          </button>
          <img
            className="chat-image-viewer__img"
            src={imageViewer.src}
            alt={imageViewer.alt || 'Preview image'}
            onClick={(event) => event.stopPropagation()}
          />
        </div>
      )}
    </div>
  )
}
