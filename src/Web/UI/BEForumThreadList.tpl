<div class="<%= $this->wrapperCss('thread-list') %>">
	<div class="<%= $this->css('thread-list-actions') %>">
		<com:THyperLink ID="NewThread" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('New thread') %> Visible="false" />
	</div>
	<table class="<%= $this->css('thread-table') %>">
		<thead>
			<tr>
				<th scope="col"><%= $this->te('Thread') %></th>
				<th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Replies') %></th>
				<th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Views') %></th>
				<th scope="col" class="<%= $this->css('col-last') %>"><%= $this->te('Last post') %></th>
			</tr>
		</thead>
		<tbody>
			<com:TRepeater ID="Threads" ItemRenderer="Belisoful\Forum\Web\UI\BEForumThreadRow">
				<prop:EmptyTemplate>
					<tr><td colspan="4" class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No threads yet.') %></td></tr>
				</prop:EmptyTemplate>
			</com:TRepeater>
		</tbody>
	</table>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
</div>
