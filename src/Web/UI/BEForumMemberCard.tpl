<div class="<%= $this->wrapperCss('member-card') %>">
	<%= $this->getAvatarHtml() %>
	<div class="<%= $this->css('member-card-name') %>"><%= $this->getNameHtml() %></div>
	<com:TRepeater ID="Badges">
		<prop:HeaderTemplate><ul class="<%= $this->TemplateControl->css('badges') %>"></prop:HeaderTemplate>
		<prop:ItemTemplate><li class="<%# $this->TemplateControl->css('badge-item') %>" title="<%# $this->Data['title'] %>"><%# $this->Data['icon'] %> <%# $this->Data['name'] %></li></prop:ItemTemplate>
		<prop:FooterTemplate></ul></prop:FooterTemplate>
	</com:TRepeater>
	<dl class="<%= $this->css('member-card-stats') %>">
		<com:TRepeater ID="Stats">
			<prop:ItemTemplate><dt><%# $this->Data['label'] %></dt><dd><%# $this->Data['value'] %></dd></prop:ItemTemplate>
		</com:TRepeater>
	</dl>
</div>
