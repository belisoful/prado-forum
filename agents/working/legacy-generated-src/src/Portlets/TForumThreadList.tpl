<div class="forum-thread-list">

  <div class="forum-thread-list__toolbar">
    <a href="<%=$this->getNewThreadUrl()%>" class="forum-btn forum-btn--primary">+ New Thread</a>
  </div>

  <% $allThreads = array_merge($this->getPinnedThreads(), $this->getThreads()); %>
  <% if (empty($allThreads)): %>
  <p class="forum-empty">No threads in this board yet. <a href="<%=$this->getNewThreadUrl()%>">Start the first one!</a></p>
  <% else: %>

  <table class="forum-thread-table" role="table">
    <thead>
      <tr>
        <th class="forum-thread-table__col-title" scope="col">Thread</th>
        <th class="forum-thread-table__col-stats" scope="col">Replies</th>
        <th class="forum-thread-table__col-views"  scope="col">Views</th>
        <th class="forum-thread-table__col-last"   scope="col">Last Post</th>
      </tr>
    </thead>
    <tbody>
      <% foreach ($allThreads as $thread): %>
      <tr class="forum-thread-row<%=$thread->is_pinned||$thread->is_sticky?' forum-thread-row--pinned':''%><%=$thread->is_locked?' forum-thread-row--locked':''%>">
        <td class="forum-thread-table__title">
          <div class="forum-thread-title-wrap">
            <% if ($thread->is_pinned || $thread->is_sticky): %>
            <span class="forum-badge forum-badge--pinned" title="Pinned">📌</span>
            <% endif; %>
            <% if ($thread->is_locked): %>
            <span class="forum-badge forum-badge--locked" title="Locked">🔒</span>
            <% endif; %>
            <a href="<%=$this->getThreadUrl($thread->id)%>" class="forum-thread-link">
              <%=htmlspecialchars($thread->title)%>
            </a>
          </div>
          <div class="forum-thread-meta">
            by <strong><%=htmlspecialchars($thread->author ? $thread->author->getEffectiveDisplayName() : 'Unknown')%></strong>
            &middot; <%=htmlspecialchars(date('M j, Y', strtotime($thread->created_at)))%>
          </div>
        </td>
        <td class="forum-thread-table__stats"><%=(int)$thread->reply_count%></td>
        <td class="forum-thread-table__views"><%=(int)$thread->view_count%></td>
        <td class="forum-thread-table__last">
          <% if ($thread->last_post_at): %>
          <span class="forum-thread-last-date"><%=htmlspecialchars(date('M j, Y', strtotime($thread->last_post_at)))%></span>
          <% endif; %>
        </td>
      </tr>
      <% endforeach; %>
    </tbody>
  </table>

  <% if ($this->getPageCount() > 1): %>
  <nav class="forum-pagination" aria-label="Thread pages">
    <% for ($p = 1; $p <= $this->getPageCount(); $p++): %>
    <a href="<%=$this->getPageUrl($p)%>"
       class="forum-pagination__link<%=$p===$this->getPage()?' forum-pagination__link--current':''%>"
       <%=$p===$this->getPage()?'aria-current="page"':''%>><%=$p%></a>
    <% endfor; %>
  </nav>
  <% endif; %>

  <% endif; %>
</div>
