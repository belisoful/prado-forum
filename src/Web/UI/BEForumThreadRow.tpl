<tr class="<%= $this->getRowCss() %>" id="thread-<%= $this->item('id') %>">
	<td class="<%= $this->css('thread-main') %>">
		<%= $this->getUnreadHtml() %>
		<%= $this->getFlagsHtml() %>
		<a class="<%= $this->css('thread-title') %>" href="<%= $this->item('url') %>"><%= $this->item('title') %></a>
		<com:TRepeater ID="Tags">
			<prop:HeaderTemplate><span class="<%= $this->TemplateControl->css('tags') %>"></prop:HeaderTemplate>
			<prop:ItemTemplate><a class="<%# $this->TemplateControl->css('tag') %>" href="<%# $this->Data['url'] %>"><%# $this->Data['name'] %></a></prop:ItemTemplate>
			<prop:FooterTemplate></span></prop:FooterTemplate>
		</com:TRepeater>
		<div class="<%= $this->css('thread-meta') %>">
			<%= $this->item('author') %> <%= $this->item('created') %> <%= $this->getBoardHtml() %>
		</div>
	</td>
	<td class="<%= $this->css('thread-count') %>"><%= $this->item('replies') %></td>
	<td class="<%= $this->css('thread-count') %>"><%= $this->item('views') %></td>
	<td class="<%= $this->css('thread-last') %>"><%= $this->getLastPostHtml() %></td>
</tr>
