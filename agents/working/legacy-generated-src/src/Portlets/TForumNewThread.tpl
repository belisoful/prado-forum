<div class="forum-new-thread">

  <% if ($this->getHasErrors()): %>
  <div class="forum-errors" role="alert">
    <ul>
      <% foreach ($this->getErrors() as $err): %>
      <li><%=htmlspecialchars($err)%></li>
      <% endforeach; %>
    </ul>
  </div>
  <% endif; %>

  <form method="post" action="" class="forum-form" enctype="multipart/form-data">
    <com:THiddenField ID="board_id" Value="<%=$this->getBoardId()%>" />

    <div class="forum-form__group">
      <label class="forum-form__label" for="forum_title">Thread Title <span class="forum-form__required">*</span></label>
      <com:TTextBox ID="forum_title" CssClass="forum-form__input" MaxLength="<%=$this->getMaxTitleLength()%>"
        Attributes.placeholder="Enter a descriptive title…" Attributes.required="required" />
    </div>

    <div class="forum-form__group">
      <label class="forum-form__label" for="forum_body">Post Body <span class="forum-form__required">*</span></label>
      <com:TTextBox ID="forum_body" TextMode="MultiLine" CssClass="forum-form__textarea forum-editor"
        Rows="12" Attributes.placeholder="Write your post here…" Attributes.required="required" />
      <p class="forum-form__hint">
        BBCode is supported: <code>[b]bold[/b]</code>, <code>[i]italic[/i]</code>,
        <code>[url=https://…]link[/url]</code>, <code>[code]…[/code]</code>,
        <code>[quote=User]…[/quote]</code>, etc.
      </p>
    </div>

    <% if ($this->getEnableTags()): %>
    <div class="forum-form__group">
      <label class="forum-form__label" for="forum_tags">Tags</label>
      <com:TTextBox ID="forum_tags" CssClass="forum-form__input"
        Attributes.placeholder="e.g. help, prado, php  (comma-separated)" />
      <p class="forum-form__hint">Up to <%=$this->getForumManager()->getMaxTagsPerThread()%> tags, separated by commas.</p>
    </div>
    <% endif; %>

    <% if ($this->getEnablePolls()): %>
    <details class="forum-form__section">
      <summary class="forum-form__section-title">Add a Poll (optional)</summary>
      <div class="forum-form__group">
        <label class="forum-form__label" for="poll_question">Poll Question</label>
        <com:TTextBox ID="poll_question" CssClass="forum-form__input" Attributes.placeholder="Your question…" />
      </div>
      <div id="poll-options-wrap">
        <div class="forum-form__group">
          <label class="forum-form__label">Options</label>
          <com:TTextBox ID="poll_opt_1" CssClass="forum-form__input forum-poll-option" Attributes.placeholder="Option 1" />
          <com:TTextBox ID="poll_opt_2" CssClass="forum-form__input forum-poll-option" Attributes.placeholder="Option 2" />
          <com:TTextBox ID="poll_opt_3" CssClass="forum-form__input forum-poll-option" Attributes.placeholder="Option 3 (optional)" />
          <com:TTextBox ID="poll_opt_4" CssClass="forum-form__input forum-poll-option" Attributes.placeholder="Option 4 (optional)" />
        </div>
        <div class="forum-form__inline">
          <label><com:TCheckBox ID="poll_multiple" /> Allow multiple choices</label>
          &nbsp;
          <label>Closes:
            <com:TTextBox ID="poll_closes_at" CssClass="forum-form__input forum-form__input--date"
              Attributes.type="datetime-local" />
          </label>
        </div>
      </div>
    </details>
    <% endif; %>

    <div class="forum-form__actions">
      <com:TButton ID="SubmitThread" Text="Post Thread" CssClass="forum-btn forum-btn--primary"
        OnClick="submitThread" />
      <a href="javascript:history.back()" class="forum-btn forum-btn--secondary">Cancel</a>
    </div>
  </form>
</div>
