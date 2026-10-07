<% if ($this->hasPoll()): %>
<% $poll = $this->getPoll(); %>
<div class="forum-poll">
  <h3 class="forum-poll__question">📊 <%=htmlspecialchars($poll->question)%></h3>

  <% if (!empty($this->getErrors())): %>
  <div class="forum-errors" role="alert">
    <ul><% foreach ($this->getErrors() as $e): %><li><%=htmlspecialchars($e)%></li><% endforeach; %></ul>
  </div>
  <% endif; %>

  <% $total = $this->getTotalVotes(); %>
  <% $showResults = $this->hasVoted() || !$poll->isOpen(); %>

  <% if ($showResults): %>
  <%-- Results view --%>
  <ul class="forum-poll__results" role="list">
    <% foreach (($poll->options ?? []) as $option): %>
    <% $pct = $this->getVotePercent((int)$option->vote_count, $total); %>
    <li class="forum-poll__option<%=in_array($option->id,$this->getUserVotes())?' forum-poll__option--voted':''%>">
      <span class="forum-poll__option-text"><%=htmlspecialchars($option->option_text)%></span>
      <div class="forum-poll__bar-wrap">
        <div class="forum-poll__bar" style="width:<%=$pct%>%" aria-valuenow="<%=$pct%>" role="progressbar"></div>
      </div>
      <span class="forum-poll__pct"><%=$pct%>% (<%=(int)$option->vote_count%> vote<%=$option->vote_count===1?'':'s'%>)</span>
    </li>
    <% endforeach; %>
  </ul>
  <p class="forum-poll__total"><%=$total%> total vote<%=$total===1?'':'s'%>
    <% if (!$poll->isOpen()): %>&nbsp;— <em>Poll closed</em><% endif; %>
  </p>
  <% if ($this->hasVoted() && $poll->allow_change_vote && $poll->isOpen()): %>
  <p class="forum-poll__change"><a href="#" class="forum-poll__change-link" onclick="document.getElementById('poll-vote-form').style.display='block';return false;">Change my vote</a></p>
  <% endif; %>

  <% else: %>
  <%-- Voting form --%>
  <form id="poll-vote-form" method="post" action="" class="forum-poll__form">
    <ul class="forum-poll__options" role="list">
      <% foreach (($poll->options ?? []) as $option): %>
      <li class="forum-poll__option">
        <label class="forum-poll__option-label">
          <input type="<%=$poll->is_multiple_choice?'checkbox':'radio'%>"
                 name="poll_option<% if ($poll->is_multiple_choice): %>[]<% endif; %>"
                 value="<%=(int)$option->id%>">
          <%=htmlspecialchars($option->option_text)%>
        </label>
      </li>
      <% endforeach; %>
    </ul>
    <% if ($poll->is_multiple_choice && $poll->max_choices > 1): %>
    <p class="forum-poll__hint">Select up to <%=(int)$poll->max_choices%> options.</p>
    <% endif; %>
    <com:TButton Text="Submit Vote" CssClass="forum-btn forum-btn--primary" OnClick="submitVote" />
  </form>
  <% endif; %>

</div>
<% endif; %>
