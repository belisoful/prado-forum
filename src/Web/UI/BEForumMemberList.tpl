<div class="<%= $this->wrapperCss('members') %>">
	<com:TPanel CssClass=<%= $this->css('members-filter') %> DefaultButton="Filter">
		<com:TTextBox ID="Query" CssClass=<%= $this->css('input') %> TextMode="Search" Attributes.placeholder=<%= $this->te('Find a member') %> ToolTip=<%= $this->te('Find a member') %> />
		<com:TDropDownList ID="SortList" CssClass=<%= $this->css('select') %> />
		<com:TButton ID="Filter" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Apply') %> OnClick="filterClicked" CausesValidation="false" />
	</com:TPanel>
	<table class="<%= $this->css('member-table') %>">
		<thead><tr><th scope="col"><%= $this->te('Member') %></th><th scope="col"><%= $this->te('Joined') %></th><th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Posts') %></th><th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Reputation') %></th></tr></thead>
		<tbody>
			<com:TRepeater ID="Rows">
				<prop:EmptyTemplate><tr><td colspan="4" class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No members found.') %></td></tr></prop:EmptyTemplate>
				<prop:ItemTemplate>
					<tr class="<%# $this->TemplateControl->css('member-row', $this->Data['banned'] ? 'banned' : null) %>">
						<td><img class="<%# $this->TemplateControl->css('avatar', 'small') %>" src="<%# $this->Data['avatar'] %>" width="32" height="32" alt="" loading="lazy" /> <%# $this->Data['name'] %> <span class="<%# $this->TemplateControl->css('muted') %>">@<%# $this->Data['username'] %></span></td>
						<td><%# $this->Data['joined'] %></td>
						<td class="<%# $this->TemplateControl->css('col-count') %>"><%# $this->Data['posts'] %></td>
						<td class="<%# $this->TemplateControl->css('col-count') %>"><%# $this->Data['reputation'] %></td>
					</tr>
				</prop:ItemTemplate>
			</com:TRepeater>
		</tbody>
	</table>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
</div>
