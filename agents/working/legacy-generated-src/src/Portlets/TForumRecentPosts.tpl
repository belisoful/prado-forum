<aside class="forum-recent-posts">
  <h3 class="forum-recent-posts__title">Recent Posts</h3>
  <% if (empty($this->getPosts())): %>
  <p class="forum-empty">No posts yet.</p>
  <% else: %>
  <ul class="forum-recent-posts__list">
    <% foreach ($this->getPosts() as $post): %>
    <li class="forum-recent-posts__item">
      <a href="<%=$this->getPostUrl($post->id)%>" class="forum-recent-posts__link">
        <%=htmlspecialchars($post->thread ? $post->thread->title : 'Thread #'.$post->thread_id)%>
      </a>
      <span class="forum-recent-posts__meta">
        by <strong><%=htmlspecialchars($post->author ? $post->author->getEffectiveDisplayName() : 'Unknown')%></strong>
        &middot; <%=htmlspecialchars(date('M j, g:i A', strtotime($post->created_at)))%>
      </span>
    </li>
    <% endforeach; %>
  </ul>
  <% endif; %>
</aside>
