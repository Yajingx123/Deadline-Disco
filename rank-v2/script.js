class TeamRanking {
  constructor() {
    this.currentUser = null;
    this.teamData = null;
    this.rankingData = [];
    this.isV2Style = this.loadStylePreference();
    this.init();
  }

  async init() {
    this.initStyle();
    await this.getUserInfo();
    await this.loadRankingData();
    if (this.currentUser) {
      await this.loadTeamData();
    }
    this.renderMyTeam();
    this.renderRanking();
    this.setupStyleToggle();
  }

  loadStylePreference() {
    const saved = localStorage.getItem('deadline_disco_style');
    return saved === 'v2';
  }

  saveStylePreference(style) {
    localStorage.setItem('deadline_disco_style', style);
  }

  initStyle() {
    if (this.isV2Style) {
      document.body.classList.add('v2-style');
    }
  }

  setupStyleToggle() {
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'style-toggle-btn';
    toggleBtn.textContent = this.isV2Style ? 'Back to Classic' : 'Try New Style';
    toggleBtn.onclick = () => this.toggleStyle();
    document.body.appendChild(toggleBtn);
  }

  toggleStyle() {
    this.isV2Style = !this.isV2Style;
    const style = this.isV2Style ? 'v2' : 'v1';
    this.saveStylePreference(style);
    
    // 如果切换到v1样式，跳转到rank界面
    if (!this.isV2Style) {
      window.location.href = '../rank/index.html';
    } else {
      // 如果切换到v2样式，刷新当前页面
      window.location.reload();
    }
  }

  async getUserInfo() {
    try {
      const response = await fetch('../Auth/backend/api/me.php', { credentials: 'include' });
      const data = await response.json();
      if (data.status === 'success' && data.user) {
        this.currentUser = data.user;
      }
    } catch (error) {
      console.error('Error getting user info:', error);
    }
  }

  async loadTeamData() {
    try {
      const response = await fetch('../challenge/api/team-ranking.php?action=get_user_team', {
        credentials: 'include'
      });
      const data = await response.json();
      if (data.status === 'success' && data.team) {
        this.teamData = data.team;
      }
    } catch (error) {
      console.error('Error loading team data:', error);
    }
  }

  async loadRankingData() {
    try {
      const response = await fetch('../challenge/api/team-ranking.php?action=get_team_ranking', {
        credentials: 'include'
      });
      const data = await response.json();
      if (data.status === 'success' && data.rankings) {
        this.rankingData = data.rankings;
      }
    } catch (error) {
      console.error('Error loading ranking data:', error);
    }
  }

  renderMyTeam() {
    const container = document.getElementById('myTeamContent');
    if (!container) return;

    if (!this.currentUser) {
      container.innerHTML = `
        <div class="no-team">
          <h3>Please Log In</h3>
          <p>You need to be logged in to see your team information.</p>
          <a href="../home.html?login=1" class="btn-primary">Log In</a>
        </div>
      `;
      return;
    }

    if (!this.teamData) {
      container.innerHTML = `
        <div class="no-team">
          <h3>Not in a Team</h3>
          <p>You haven't joined a team yet. Create or join a team to participate in the weekly challenge.</p>
          <a href="../home.html?challenge=1" class="btn-primary">Join Challenge</a>
        </div>
      `;
      return;
    }

    const membersHtml = this.teamData.members && this.teamData.members.length > 0
      ? this.teamData.members.map(member => `
          <div class="member-item">
            <div class="member-avatar-large">${this.getInitials(member.username)}</div>
            <span class="member-name">${member.username}</span>
            <span class="member-role">${member.member_role === 'captain' ? 'Captain' : 'Member'}</span>
          </div>
        `).join('')
      : '<p>No members</p>';

    const progressHtml = this.renderDailyProgress(this.teamData.daily_progress);
    const weeklyProgressHtml = this.renderWeeklyProgress(this.teamData.weekly_progress);

    container.innerHTML = `
      <div class="team-info">
        <div class="team-header">
          <div class="team-title">
            <h3>${this.escapeHtml(this.teamData.team_name)}</h3>
            <p>Captain: ${this.escapeHtml(this.teamData.captain_username)}</p>
          </div>
          <span class="team-status ${this.teamData.status}">
            ${this.teamData.status === 'locked' ? 'Active' : this.teamData.status}
          </span>
        </div>
        <div class="team-details">
          <div class="detail-item">
            <span class="detail-label">Current Rank</span>
            <span class="detail-value">#${this.teamData.rank || '-'}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Team Score</span>
            <span class="detail-value">${this.teamData.score || 0} pts</span>
          </div>
        </div>
        <div class="team-members">
          <h4>Team Members</h4>
          <div class="members-list">
            ${membersHtml}
          </div>
        </div>
        <div class="team-progress">
          <h4>Daily Progress</h4>
          <div class="progress-list">
            ${progressHtml}
          </div>
        </div>
        <div class="team-progress">
          <h4>Weekly Match Progress</h4>
          <div class="progress-list">
            ${weeklyProgressHtml}
          </div>
        </div>
      </div>
    `;
  }

  renderRanking() {
    const container = document.getElementById('rankingContent');
    if (!container) return;

    if (!this.rankingData || this.rankingData.length === 0) {
      container.innerHTML = `
        <div class="loading-state">
          <p>No team rankings available yet.</p>
          <p style="font-size: 14px; margin-top: 8px;">Teams will appear here once they are formed.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = this.rankingData.map((team, index) => {
      const isCurrentTeam = this.teamData && team.team_id === this.teamData.team_id;
      const rankClass = index < 3 ? `rank-${index + 1}` : '';
      const currentClass = isCurrentTeam ? 'current-team' : '';

      const membersHtml = team.members && team.members.length > 0
        ? team.members.slice(0, 4).map(member => `
            <div class="member-avatar" title="${this.escapeHtml(member.username)}">
              ${this.getInitials(member.username)}
            </div>
          `).join('')
        : '';

      return `
        <div class="ranking-row ${rankClass} ${currentClass}">
          <div class="rank-col ${index < 3 ? 'top-3' : ''}">${index + 1}</div>
          <div class="team-col">
            <div class="team-name">${this.escapeHtml(team.team_name)}</div>
            <div class="team-captain">Captain: ${this.escapeHtml(team.captain_username)}</div>
          </div>
          <div class="members-col">
            ${membersHtml}
          </div>
          <div class="score-col">${team.score || 0}</div>
        </div>
      `;
    }).join('');
  }

  renderDailyProgress(progress) {
    const ruleNames = {
      'routine1': 'Daily Check-in',
      'routine2': 'Online Learning Time',
      'routine3': 'Post Creation',
      'routine4': 'Reply to Posts'
    };

    if (!progress || progress.length === 0) {
      const defaultRules = ['routine1', 'routine2', 'routine3', 'routine4'];
      return defaultRules.map(ruleId => `
        <div class="progress-item">
          <div class="progress-header">
            <span class="progress-name">${ruleNames[ruleId] || ruleId}</span>
            <span class="progress-stats">0/?</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width: 0%;"></div>
          </div>
          <div class="progress-points">0 pts</div>
        </div>
      `).join('');
    }

    return progress.map(item => {
      const displayName = ruleNames[item.rule_id] || item.rule_name;
      return `
        <div class="progress-item">
          <div class="progress-header">
            <span class="progress-name">${displayName}</span>
            <span class="progress-stats">${item.current_score}/${item.max_points}</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width: ${item.percentage}%;"></div>
          </div>
          <div class="progress-points">${item.current_score} pts</div>
        </div>
      `;
    }).join('');
  }

  renderWeeklyProgress(progress) {
    if (!progress) {
      return `
        <div class="progress-item">
          <div class="progress-header">
            <span class="progress-name">Weekly Matches</span>
            <span class="progress-stats">0 W - 0 L</span>
          </div>
          <div class="progress-bar">
            <div class="progress-fill" style="width: 0%;"></div>
          </div>
          <div class="progress-points">0 pts</div>
        </div>
      `;
    }

    const totalMatches = progress.win_count + progress.lose_count;
    const winRate = totalMatches > 0 ? (progress.win_count / totalMatches) * 100 : 0;

    return `
      <div class="progress-item">
        <div class="progress-header">
          <span class="progress-name">Weekly Matches</span>
          <span class="progress-stats">${progress.win_count} W - ${progress.lose_count} L</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: ${Math.min(100, winRate)}%;"></div>
        </div>
        <div class="progress-points">${progress.total_score} pts</div>
      </div>
    `;
  }

  getInitials(name) {
    if (!name) return 'UN';
    const trimmed = String(name).trim();
    if (trimmed.length <= 2) return trimmed.toUpperCase();
    return trimmed.slice(0, 2).toUpperCase();
  }

  escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  new TeamRanking();
});
