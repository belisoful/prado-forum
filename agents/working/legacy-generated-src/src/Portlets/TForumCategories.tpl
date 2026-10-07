<div class="forum-categories">
  <% foreach ($this->getCategories() as $category): %>
  <section class="forum-category">
    <h2 class="forum-category__title"><%=htmlspecialchars($category->name)%></h2>
    <% if ($category->description): %>
    <p class="forum-category__desc"><%=htmlspecialchars($category->description)%></p>
    <% endif; %>

    <div class="forum-boards">
      <% foreach (($category->boards ?? []) as $board): %>
      <% if (!$board->is_active) continue; %>
      <div class="forum-board">
        <div class="forum-board__icon">
          <% if ($board->icon_url): %>
          <img src="<%=htmlspecialchars($board->icon_url)%>" alt="" width="40" height="40">
          <% else: %>
          <span class="forum-board__icon-default" aria-hidden="true">💬</span>
          <% endif; %>
        </div>

        <div class="forum-board__info">
          <h3 class="forum-board__name">
            <a href="<%=$this->getBoardUrl($board->id)%>"><%=htmlspecialchars($board->name)%></a>
            <% if ($board->is_locked): %>
            <span class="forum-board__badge forum-board__badge--locked" title="Locked">🔒</span>
            <% endif; %>
          </h3>
          <% if ($board->description): %>
          <p class="forum-board__desc"><%=htmlspecialchars($board->description)%></p>
          <% endif; %>
        </div>

        <div class="forum-board__stats">
          <span class="forum-board__stat"><strong><%=(int)$board->thread_count%></strong> threads</span>
          <span class="forum-board__stat"><strong><%=(int)$board->post_count%></strong> posts</span>
        </div>

        <div class="forum-board__last-post">
          <% if ($board->last_post_at): %>
          <span class="forum-board__last-post-date"><%=htmlspecialchars(date('M j, Y', strtotime($board->last_post_at)))%></span>
          <% if ($board->last_post_id): %>
          <a href="<%=$this->getThreadUrl($board->last_post_id)%>" class="forum-board__last-post-link">View last post</a>
          <% endif; %>
          <% else: %>
          <span class="forum-board__no-posts">No posts yet</span>
          <% endif; %>
        </div>
      </div>
      <% endforeach; %>
    </div>
  </section>
  <% endforeach; %>

  <% if (empty($this->getCategories())): %>
  <p class="forum-empty">No forum categories have been created yet.</p>
  <% endif; %>
</div>
