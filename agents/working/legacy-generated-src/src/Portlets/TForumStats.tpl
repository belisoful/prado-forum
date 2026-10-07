<aside class="forum-stats">
  <h3 class="forum-stats__title">Forum Statistics</h3>
  <% $s = $this->getStats(); %>
  <ul class="forum-stats__list">
    <li class="forum-stats__item">
      <span class="forum-stats__label">Threads</span>
      <strong class="forum-stats__value"><%=(int)($s['threads']??0)%></strong>
    </li>
    <li class="forum-stats__item">
      <span class="forum-stats__label">Posts</span>
      <strong class="forum-stats__value"><%=(int)($s['posts']??0)%></strong>
    </li>
    <li class="forum-stats__item">
      <span class="forum-stats__label">Members</span>
      <strong class="forum-stats__value"><%=(int)($s['members']??0)%></strong>
    </li>
    <% if (!empty($s['newest_member'])): %>
    <li class="forum-stats__item">
      <span class="forum-stats__label">Newest member</span>
      <a class="forum-stats__value" href="<%=$this->getMemberUrl($s['newest_member']['username'])%>">
        <%=htmlspecialchars($s['newest_member']['display_name'])%>
      </a>
    </li>
    <% endif; %>
  </ul>
</aside>
