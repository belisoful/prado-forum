<div class="<%= $this->wrapperCss('subscriptions') %>">
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<table class="<%= $this->css('subscription-table') %>">
		<thead><tr><th scope="col"><%= $this->te('Type') %></th><th scope="col"><%= $this->te('Subscription') %></th><th scope="col"><%= $this->te('Since') %></th><th scope="col"></th></tr></thead>
		<tbody>
			<com:TRepeater ID="Rows" OnItemCommand="itemCommand">
				<prop:EmptyTemplate><tr><td colspan="4" class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('You have no subscriptions.') %></td></tr></prop:EmptyTemplate>
				<prop:ItemTemplate>
					<tr>
						<td><%# $this->Data['typeLabel'] %></td>
						<td><com:TLiteral Text=<%# $this->Data['url'] !== '' ? '<a href="' . $this->Data['url'] . '">' . $this->Data['title'] . '</a>' : $this->Data['title'] %> /></td>
						<td><%# $this->Data['since'] %></td>
						<td><com:TLinkButton CssClass=<%# $this->TemplateControl->css('action', 'danger') %> Text=<%# $this->TemplateControl->te('Unsubscribe') %> CommandName="unsubscribe" CommandParameter=<%# $this->Data['type'] . ':' . $this->Data['target'] %> CausesValidation="false" /></td>
					</tr>
				</prop:ItemTemplate>
			</com:TRepeater>
		</tbody>
	</table>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
</div>
