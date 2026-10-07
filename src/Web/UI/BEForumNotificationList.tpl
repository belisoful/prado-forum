<div class="<%= $this->wrapperCss('notifications') %>">
	<div class="<%= $this->css('notifications-actions') %>">
		<com:THyperLink ID="UnreadLink" CssClass=<%= $this->css('button', 'link') %> />
		<com:TLinkButton ID="MarkAll" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Mark all read') %> OnClick="markAllClicked" CausesValidation="false" />
	</div>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<ul class="<%= $this->css('notification-list') %>">
		<com:TRepeater ID="Rows" OnItemCommand="itemCommand">
			<prop:EmptyTemplate><li class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No notifications.') %></li></prop:EmptyTemplate>
			<prop:ItemTemplate>
				<li class="<%# $this->TemplateControl->css('notification', $this->Data['read'] ? 'read' : 'unread') %> <%# $this->TemplateControl->css('notification', $this->Data['type']) %>">
					<com:TLiteral Text=<%# $this->Data['url'] !== '' ? '<a class="' . $this->TemplateControl->css('notification-link') . '" href="' . $this->Data['url'] . '">' . $this->Data['message'] . '</a>' : '<span class="' . $this->TemplateControl->css('notification-link') . '">' . $this->Data['message'] . '</span>' %> />
					<span class="<%# $this->TemplateControl->css('notification-time') %>"><%# $this->Data['time'] %></span>
					<span class="<%# $this->TemplateControl->css('notification-actions') %>">
						<com:TLinkButton CssClass=<%# $this->TemplateControl->css('action') %> Text=<%# $this->TemplateControl->te('Mark read') %> CommandName="read" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" Visible=<%# !$this->Data['read'] %> />
						<com:TLinkButton CssClass=<%# $this->TemplateControl->css('action', 'danger') %> Text=<%# $this->TemplateControl->te('Delete') %> CommandName="delete" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
					</span>
				</li>
			</prop:ItemTemplate>
		</com:TRepeater>
	</ul>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
</div>
