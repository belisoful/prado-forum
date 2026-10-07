<div class="forum-reply-portlet">
  <% if ($this->getIsLocked()): %>
  <p class="forum-notice forum-notice--locked">🔒 This thread is locked. No new replies can be posted.</p>
  <% else: %>

  <% if ($this->getHasErrors()): %>
  <div class="forum-errors" role="alert">
    <ul>
      <% foreach ($this->getErrors() as $err): %>
      <li><%=htmlspecialchars($err)%></li>
      <% endforeach; %>
    </ul>
  </div>
  <% endif; %>

  <form method="post" action="" class="forum-form forum-reply-form">
    <div class="forum-form__group">
      <label class="forum-form__label" for="forum_reply_body">Your Reply <span class="forum-form__required">*</span></label>
      <com:TTextBox ID="forum_reply_body" TextMode="MultiLine" CssClass="forum-form__textarea forum-editor"
        Rows="8" Attributes.placeholder="Write your reply here…" Attributes.required="required" />
      <p class="forum-form__hint">BBCode supported. Use <code>[quote=Author]…[/quote]</code> to quote a post.</p>
    </div>
    <div class="forum-form__actions">
      <com:TButton Text="Post Reply" CssClass="forum-btn forum-btn--primary" OnClick="submitReply" />
    </div>
  </form>
  <% endif; %>
</div>
