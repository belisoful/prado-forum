<div class="forum-search">
  <form method="get" action="<%=$this->getSearchAction()%>" class="forum-search__form" role="search">
    <div class="forum-search__row">
      <label class="forum-sr-only" for="q">Search forum</label>
      <com:TTextBox ID="q" CssClass="forum-search__input" Value="<%=$this->getQuery()%>"
        Attributes.name="q" Attributes.placeholder="Search the forum…" Attributes.autocomplete="off" />
      <button type="submit" class="forum-btn forum-btn--primary">Search</button>
    </div>

    <div class="forum-search__filters">
      <label>
        <input type="radio" name="in" value="threads" <%=$this->getSearchIn()==='threads'?'checked':''%>> Threads
      </label>
      <label>
        <input type="radio" name="in" value="posts" <%=$this->getSearchIn()==='posts'?'checked':''%>> Posts
      </label>

      <% if (!empty($this->getBoards())): %>
      <select name="board" class="forum-search__board-filter">
        <option value="0"<%=$this->getBoardId()===0?' selected':''%>>All boards</option>
        <% foreach ($this->getBoards() as $board): %>
        <option value="<%=(int)$board->id%>"<%=$this->getBoardId()===$board->id?' selected':''%>>
          <%=htmlspecialchars($board->name)%>
        </option>
        <% endforeach; %>
      </select>
      <% endif; %>
    </div>
  </form>

  <% if ($this->getShowResults() && $this->getQuery() !== ''): %>
  <div class="forum-search__results">
    <p class="forum-search__summary">
      <%=(int)$this->getTotal()%> result<%=$this->getTotal()===1?'':'s'%>
      for <strong><%=htmlspecialchars($this->getQuery())%></strong>
    </p>

    <% if (empty($this->getResults())): %>
    <p class="forum-empty">No results found.</p>
    <% else: %>
    <ul class="forum-search__list">
      <% foreach ($this->getResults() as $record): %>
      <li class="forum-search__item">
        <a href="<%=$this->getResultUrl($record)%>" class="forum-search__link">
          <% if (isset($record->title)): %>
          <%=htmlspecialchars($record->title)%>
          <% else: %>
          Post #<%=(int)$record->id%>
          <% endif; %>
        </a>
        <span class="forum-search__date"><%=htmlspecialchars(date('M j, Y', strtotime($record->created_at)))%></span>
      </li>
      <% endforeach; %>
    </ul>

    <% if ($this->getPageCount() > 1): %>
    <nav class="forum-pagination">
      <% for ($p = 1; $p <= $this->getPageCount(); $p++): %>
      <a href="<%=$this->getPageUrl($p)%>" class="forum-pagination__link<%=$p===$this->getPage()?' forum-pagination__link--current':''%>"><%=$p%></a>
      <% endfor; %>
    </nav>
    <% endif; %>
    <% endif; %>
  </div>
  <% endif; %>
</div>
