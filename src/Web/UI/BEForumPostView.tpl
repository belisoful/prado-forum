<article class="<%= $this->getRowCss() %>" id="post-<%= $this->item('id') %>">
	<aside class="<%= $this->css('post-author') %>">
		<com:Belisoful\Forum\Web\UI\BEForumMemberCard ID="Author" />
	</aside>
	<div class="<%= $this->css('post-body') %>">
		<header class="<%= $this->css('post-header') %>">
			<a class="<%= $this->css('post-permalink') %>" href="<%= $this->item('url') %>" title="<%= $this->te('Permalink') %>">#<%= $this->item('position') %></a>
			<%= $this->item('created') %>
			<%= $this->getContextHtml() %>
			<%= $this->getReplyToHtml() %>
		</header>
		<div class="<%= $this->css('post-status-line') %>"><%= $this->getStatusHtml() %></div>
		<div class="<%= $this->css('post-content') %>"><%= $this->item('html') %></div>
		<com:TRepeater ID="Attachments">
			<prop:HeaderTemplate><ul class="<%= $this->TemplateControl->css('attachments') %>"></prop:HeaderTemplate>
			<prop:ItemTemplate><li class="<%# $this->TemplateControl->css('attachment') %>"><com:TLiteral Text=<%# $this->Data['image'] ? '<a href="' . $this->Data['url'] . '"><img class="' . $this->TemplateControl->css('attachment-image') . '" src="' . $this->Data['url'] . '" alt="' . $this->Data['name'] . '" loading="lazy" /></a>' : '' %> /><a href="<%# $this->Data['url'] %>"><%# $this->Data['name'] %></a> <span class="<%# $this->TemplateControl->css('muted') %>">(<%# $this->Data['size'] %>)</span></li></prop:ItemTemplate>
			<prop:FooterTemplate></ul></prop:FooterTemplate>
		</com:TRepeater>
		<com:TLiteral Text=<%= $this->item('signature', '') !== '' ? '<div class="' . $this->css('post-signature') . '">' . $this->item('signature') . '</div>' : '' %> />
		<footer class="<%= $this->css('post-footer') %>">
			<com:Belisoful\Forum\Web\UI\BEForumReactionBar ID="Reactions" />
			<div class="<%= $this->css('post-actions') %>">
				<com:TLinkButton ID="Quote" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Quote') %> CommandName="quote" CommandParameter=<%# $this->getPostID() %> CausesValidation="false" />
				<com:THyperLink ID="Edit" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Edit') %> />
				<com:TLinkButton ID="Delete" CssClass=<%= $this->css('action', 'danger') %> Text=<%= $this->te('Delete') %> OnClick="deleteClicked" CausesValidation="false" Attributes.onclick=<%= $this->confirmScript('Delete this post?') %> />
				<com:TLinkButton ID="Restore" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Restore') %> OnClick="restoreClicked" CausesValidation="false" />
				<com:TLinkButton ID="Approve" CssClass=<%= $this->css('action', 'primary') %> Text=<%= $this->te('Approve') %> OnClick="approveClicked" CausesValidation="false" />
				<com:TLinkButton ID="Accept" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Accept answer') %> OnClick="acceptClicked" CausesValidation="false" />
				<com:TLinkButton ID="Unaccept" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Unaccept answer') %> OnClick="unacceptClicked" CausesValidation="false" />
				<com:TLinkButton ID="Bookmark" CssClass=<%= $this->css('action') %> OnClick="bookmarkClicked" CausesValidation="false" />
				<com:TLinkButton ID="Report" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Report') %> OnClick="reportClicked" CausesValidation="false" />
			</div>
			<com:TPanel ID="ReportPanel" CssClass=<%= $this->css('report-form') %> Visible="false">
				<label><%= $this->te('Why are you reporting this post?') %>
					<com:TTextBox ID="ReportReason" TextMode="MultiLine" Rows="3" CssClass=<%= $this->css('input') %> />
				</label>
				<com:TLinkButton CssClass=<%= $this->css('button', 'small') %> Text=<%= $this->te('Send report') %> OnClick="sendReportClicked" CausesValidation="false" />
				<com:TLinkButton CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('Cancel') %> OnClick="cancelReportClicked" CausesValidation="false" />
			</com:TPanel>
			<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
		</footer>
	</div>
</article>
