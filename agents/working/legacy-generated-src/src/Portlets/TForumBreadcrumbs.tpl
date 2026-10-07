<nav class="forum-breadcrumbs" aria-label="Breadcrumb">
  <ol class="forum-breadcrumbs__list">
    <% foreach ($this->getCrumbs() as $i => $crumb): %>
    <li class="forum-breadcrumbs__item<%=$i === count($this->getCrumbs())-1 ? ' forum-breadcrumbs__item--current' : ''%>">
      <% if (!empty($crumb['url'])): %>
      <a href="<%=$crumb['url']%>"><%=htmlspecialchars($crumb['label'])%></a>
      <% else: %>
      <span aria-current="page"><%=htmlspecialchars($crumb['label'])%></span>
      <% endif; %>
      <% if ($i < count($this->getCrumbs())-1): %><span class="forum-breadcrumbs__sep" aria-hidden="true">›</span><% endif; %>
    </li>
    <% endforeach; %>
  </ol>
</nav>
