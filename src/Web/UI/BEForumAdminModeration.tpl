<div class="<%= $this->wrapperCss('admin') %>">
	<h1 class="<%= $this->css('admin-title') %>"><%= $this->te('Moderation') %></h1>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<section class="<%= $this->css('admin-section') %>">
		<h2><%= $this->te('Open reports') %></h2>
		<com:TRepeater ID="Reports" OnItemCommand="reportCommand">
			<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No open reports.') %></p></prop:EmptyTemplate>
			<prop:ItemTemplate>
				<div class="<%# $this->TemplateControl->css('report') %>">
					<div class="<%# $this->TemplateControl->css('report-post') %>"><a href="<%# $this->Data['url'] %>"><%# $this->Data['excerpt'] %></a> <span class="<%# $this->TemplateControl->css('muted') %>">&mdash; <%# $this->Data['author'] %></span></div>
					<div class="<%# $this->TemplateControl->css('report-reason') %>"><%# $this->Data['reporter'] %> <%# $this->Data['time'] %>: <%# $this->Data['reason'] %></div>
					<div class="<%# $this->TemplateControl->css('report-actions') %>">
						<com:TTextBox ID="Note" CssClass=<%# $this->TemplateControl->css('input', 'inline') %> Attributes.placeholder=<%# $this->TemplateControl->t('Resolution note') %> />
						<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'small') %> Text=<%# $this->TemplateControl->te('Resolve') %> CommandName="resolve" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
						<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'link') %> Text=<%# $this->TemplateControl->te('Dismiss') %> CommandName="dismiss" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
					</div>
				</div>
			</prop:ItemTemplate>
		</com:TRepeater>
		<com:Belisoful\Forum\Web\UI\BEForumPager ID="ReportPager" />
	</section>
	<section class="<%= $this->css('admin-section') %>">
		<h2><%= $this->te('Awaiting approval') %></h2>
		<com:TRepeater ID="Pending" OnItemCommand="pendingCommand">
			<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('Nothing awaits approval.') %></p></prop:EmptyTemplate>
			<prop:ItemTemplate>
				<div class="<%# $this->TemplateControl->css('pending') %>">
					<span class="<%# $this->TemplateControl->css('badge') %>"><%# $this->Data['label'] %></span>
					<a href="<%# $this->Data['url'] %>"><%# $this->Data['title'] %></a>
					<span class="<%# $this->TemplateControl->css('muted') %>"><%# $this->Data['author'] %> <%# $this->Data['time'] %></span>
					<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'small') %> Text=<%# $this->TemplateControl->te('Approve') %> CommandName="approve" CommandParameter=<%# $this->Data['kind'] . ':' . $this->Data['id'] %> CausesValidation="false" />
					<com:TLinkButton CssClass=<%# $this->TemplateControl->css('button', 'link') %> Text=<%# $this->TemplateControl->te('Reject') %> CommandName="reject" CommandParameter=<%# $this->Data['kind'] . ':' . $this->Data['id'] %> CausesValidation="false" />
				</div>
			</prop:ItemTemplate>
		</com:TRepeater>
	</section>
	<section class="<%= $this->css('admin-section') %>">
		<h2><%= $this->te('Moderation log') %></h2>
		<table class="<%= $this->css('log-table') %>">
			<thead><tr><th scope="col"><%= $this->te('When') %></th><th scope="col"><%= $this->te('Who') %></th><th scope="col"><%= $this->te('Action') %></th><th scope="col"><%= $this->te('Target') %></th><th scope="col"><%= $this->te('Details') %></th></tr></thead>
			<tbody>
				<com:TRepeater ID="Log">
					<prop:EmptyTemplate><tr><td colspan="5" class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('The log is empty.') %></td></tr></prop:EmptyTemplate>
					<prop:ItemTemplate><tr><td><%# $this->Data['time'] %></td><td><%# $this->Data['who'] %></td><td><%# $this->Data['action'] %></td><td><%# $this->Data['target'] %></td><td class="<%# $this->TemplateControl->css('muted') %>"><%# $this->Data['details'] %></td></tr></prop:ItemTemplate>
				</com:TRepeater>
			</tbody>
		</table>
		<com:Belisoful\Forum\Web\UI\BEForumPager ID="LogPager" />
	</section>
</div>
