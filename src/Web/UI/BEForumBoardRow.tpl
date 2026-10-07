<tr class="<%= $this->css('board', $this->item('modifier')) %>">
	<td class="<%= $this->css('board-main') %>">
		<a class="<%= $this->css('board-name') %>" href="<%= $this->item('url') %>"><%= $this->item('name') %></a>
		<%= $this->getFlagsHtml() %>
		<com:TLiteral Text=<%= $this->item('description', '') !== '' ? '<div class="' . $this->css('board-description') . '">' . $this->item('description') . '</div>' : '' %> />
		<com:TRepeater ID="SubBoards">
			<prop:HeaderTemplate><ul class="<%= $this->TemplateControl->css('sub-boards') %>"></prop:HeaderTemplate>
			<prop:ItemTemplate><li><a class="<%# $this->TemplateControl->css('sub-board', $this->Data['unread'] ? 'unread' : null) %>" href="<%# $this->Data['url'] %>"><%# $this->Data['name'] %></a></li></prop:ItemTemplate>
			<prop:FooterTemplate></ul></prop:FooterTemplate>
		</com:TRepeater>
	</td>
	<td class="<%= $this->css('board-count') %>"><%= $this->item('threads') %></td>
	<td class="<%= $this->css('board-count') %>"><%= $this->item('posts') %></td>
	<td class="<%= $this->css('board-last') %>"><%= $this->getLastPostHtml() %></td>
</tr>
