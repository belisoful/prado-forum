<div class="<%= $this->wrapperCss('admin') %>">
	<h1 class="<%= $this->css('admin-title') %>"><%= $this->te('Member administration') %></h1>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<com:TPanel CssClass=<%= $this->css('members-filter') %> DefaultButton="Filter">
		<com:TTextBox ID="Query" CssClass=<%= $this->css('input') %> TextMode="Search" Attributes.placeholder=<%= $this->te('Find a member') %> ToolTip=<%= $this->te('Find a member') %> />
		<com:TButton ID="Filter" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Search') %> OnClick="filterClicked" CausesValidation="false" />
	</com:TPanel>
	<table class="<%= $this->css('member-table') %>">
		<thead><tr><th scope="col"><%= $this->te('Member') %></th><th scope="col"><%= $this->te('Joined') %></th><th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Posts') %></th><th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Warnings') %></th><th scope="col"><%= $this->te('Actions') %></th></tr></thead>
		<tbody>
			<com:TRepeater ID="Rows" OnItemCommand="memberCommand">
				<prop:EmptyTemplate><tr><td colspan="5" class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No members found.') %></td></tr></prop:EmptyTemplate>
				<prop:ItemTemplate>
					<tr class="<%# $this->TemplateControl->css('member-row', $this->Data['banned'] ? 'banned' : null) %>">
						<td><%# $this->Data['name'] %> <span class="<%# $this->TemplateControl->css('muted') %>">@<%# $this->Data['username'] %> <%# $this->Data['status'] %></span></td>
						<td><%# $this->Data['joined'] %></td>
						<td class="<%# $this->TemplateControl->css('col-count') %>"><%# $this->Data['posts'] %></td>
						<td class="<%# $this->TemplateControl->css('col-count') %>"><%# $this->Data['warnings'] %></td>
						<td class="<%# $this->TemplateControl->css('admin-row-actions') %>">
							<com:TTextBox ID="Reason" CssClass=<%# $this->TemplateControl->css('input', 'inline') %> Attributes.placeholder=<%# $this->TemplateControl->t('Reason') %> MaxLength="500" />
							<com:TTextBox ID="Until" TextMode="DatetimeLocal" CssClass=<%# $this->TemplateControl->css('input', 'inline') %> Visible=<%# !$this->Data['banned'] %> />
							<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'small') %> Text=<%# $this->TemplateControl->te('Warn') %> CommandName="warn" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
							<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'danger') %> Text=<%# $this->TemplateControl->te('Ban') %> CommandName="ban" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" Visible=<%# !$this->Data['banned'] %> />
							<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'small') %> Text=<%# $this->TemplateControl->te('Lift ban') %> CommandName="unban" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" Visible=<%# $this->Data['banned'] %> />
						</td>
					</tr>
				</prop:ItemTemplate>
			</com:TRepeater>
		</tbody>
	</table>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
	<com:TPanel ID="BadgePanel" CssClass=<%= $this->css('admin-section') %>>
		<h2><%= $this->te('Badges') %></h2>
		<ul class="<%= $this->css('badges') %>">
			<com:TRepeater ID="Badges">
				<prop:ItemTemplate><li class="<%# $this->TemplateControl->css('badge-item') %>" title="<%# $this->Data['description'] %>"><%# $this->Data['name'] %> <span class="<%# $this->TemplateControl->css('muted') %>">(<%# $this->Data['slug'] %>)</span></li></prop:ItemTemplate>
			</com:TRepeater>
		</ul>
		<com:TPanel CssClass=<%= $this->css('editor-form') %> DefaultButton="DefineBadge">
			<label for="<%= $this->BadgeName->getClientID() %>"><%= $this->te('Badge name') %></label>
			<com:TTextBox ID="BadgeName" CssClass=<%= $this->css('input') %> MaxLength="120" />
			<label for="<%= $this->BadgeDescription->getClientID() %>"><%= $this->te('Description') %></label>
			<com:TTextBox ID="BadgeDescription" CssClass=<%= $this->css('input') %> MaxLength="500" />
			<label for="<%= $this->BadgeIcon->getClientID() %>"><%= $this->te('Icon URL or CSS class') %></label>
			<com:TTextBox ID="BadgeIcon" CssClass=<%= $this->css('input') %> MaxLength="255" />
			<com:TButton ID="DefineBadge" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Save badge') %> OnClick="defineBadgeClicked" CausesValidation="false" />
		</com:TPanel>
	</com:TPanel>
</div>
