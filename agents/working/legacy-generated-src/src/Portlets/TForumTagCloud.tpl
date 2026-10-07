<aside class="forum-tag-cloud">
  <h3 class="forum-tag-cloud__title">Popular Tags</h3>
  <% if (empty($this->getTags())): %>
  <p class="forum-empty">No tags yet.</p>
  <% else: %>
  <div class="forum-tag-cloud__cloud" aria-label="Tag cloud">
    <% foreach ($this->getTags() as $tag): %>
    <a href="<%=$this->getTagUrl($tag->slug)%>"
       class="forum-tag"
       style="font-size:<%=$this->getTagSize($tag)%>em"
       title="<%=(int)$tag->thread_count%> thread<%=$tag->thread_count===1?'':'s'%>">
      <%=htmlspecialchars($tag->name)%>
    </a>
    <% endforeach; %>
  </div>
  <% endif; %>
</aside>
