<div class="forum-post-list">

  <% foreach ($this->getPosts() as $i => $post): %>
  <article id="post-<%=(int)$post->id%>" class="forum-post">

    <aside class="forum-post__author">
      <% $profile = $post->author; %>
      <% $avatarUrl = $profile && $profile->avatar_url ? $profile->avatar_url : ''; %>
      <% if ($avatarUrl): %>
      <img class="forum-post__avatar" src="<%=htmlspecialchars($avatarUrl)%>" alt="" width="60" height="60" loading="lazy">
      <% else: %>
      <div class="forum-post__avatar forum-post__avatar--default" aria-hidden="true">👤</div>
      <% endif; %>

      <a href="<%=$this->getForumManager()->createUrl('forum/UserProfile',['username'=>$profile?$profile->username:''])%>"
         class="forum-post__username">
        <%=htmlspecialchars($profile ? $profile->getEffectiveDisplayName() : 'Unknown')%>
      </a>

      <% if ($profile): %>
      <span class="forum-post__post-count"><%=(int)$profile->post_count%> posts</span>
      <% if ($profile->reputation_points): %>
      <span class="forum-post__rep" title="Reputation">⭐ <%=(int)$profile->reputation_points%></span>
      <% endif; %>
      <% endif; %>
    </aside>

    <div class="forum-post__body-wrap">
      <header class="forum-post__header">
        <time class="forum-post__date" datetime="<%=htmlspecialchars($post->created_at)%>">
          <%=htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($post->created_at)))%>
        </time>
        <% if ($post->edited_at): %>
        <span class="forum-post__edited" title="Edited <%=htmlspecialchars(date('M j, Y', strtotime($post->edited_at)))%>">
          (edited<% if ($post->edit_reason): %> — <%=htmlspecialchars($post->edit_reason)%><% endif; %>)
        </span>
        <% endif; %>
        <a class="forum-post__permalink" href="#post-<%=(int)$post->id%>" aria-label="Permalink">#<%=(int)$post->id%></a>
      </header>

      <div class="forum-post__content bbcode-content">
        <%=$post->content_html%>
      </div>

      <% if ($post->author && $post->author->signature && $this->getForumManager()->getEnableSignatures()): %>
      <footer class="forum-post__signature">
        <hr>
        <div class="forum-post__sig-body"><%=htmlspecialchars($post->author->signature)%></div>
      </footer>
      <% endif; %>

      <div class="forum-post__actions">

        <% if ($this->getForumManager()->getEnableReactions()): %>
        <div class="forum-post__reactions">
          <% $summary = $this->getReactionSummary($post->id); %>
          <% foreach ($this->getReactionTypes() as $type): %>
          <% if (isset($summary[$type])): %>
          <span class="forum-reaction forum-reaction--<%=htmlspecialchars($type)%>">
            <%=htmlspecialchars($type)%> <%=(int)$summary[$type]%>
          </span>
          <% endif; %>
          <% endforeach; %>
          <a href="<%=$this->getReactUrl($post->id)%>" class="forum-action-link forum-action-link--react">React</a>
        </div>
        <% endif; %>

        <div class="forum-post__links">
          <a href="<%=$this->getEditUrl($post->id)%>" class="forum-action-link">Edit</a>
          <a href="<%=$this->getReportUrl($post->id)%>" class="forum-action-link forum-action-link--report">Report</a>
        </div>
      </div>
    </div>

  </article>
  <% endforeach; %>

  <% if ($this->getPageCount() > 1): %>
  <nav class="forum-pagination" aria-label="Post pages">
    <% for ($p = 1; $p <= $this->getPageCount(); $p++): %>
    <a href="<%=$this->getPageUrl($p)%>"
       class="forum-pagination__link<%=$p===$this->getPage()?' forum-pagination__link--current':''%>"
       <%=$p===$this->getPage()?'aria-current="page"':''%>><%=$p%></a>
    <% endfor; %>
  </nav>
  <% endif; %>

</div>
