<div class="<%= $this->wrapperCss('reactions') %>">
	<com:TRepeater ID="Buttons" OnItemCommand="reactionCommand">
		<prop:ItemTemplate>
			<com:TLinkButton CssClass=<%# $this->TemplateControl->css('reaction', $this->Data['active'] ? 'active' : null) %> CommandName=<%# $this->Data['type'] %> CausesValidation="false" ToolTip=<%# $this->Data['label'] %>>
				<span class="<%# $this->TemplateControl->css('reaction-symbol') %>"><%# $this->Data['symbol'] %></span>
				<span class="<%# $this->TemplateControl->css('reaction-count') %>"><%# $this->Data['count'] > 0 ? $this->Data['count'] : '' %></span>
			</com:TLinkButton>
		</prop:ItemTemplate>
	</com:TRepeater>
	<com:TRepeater ID="Counts">
		<prop:ItemTemplate>
			<span class="<%# $this->TemplateControl->css('reaction', 'static') %>" title="<%# $this->Data['label'] %>"><span class="<%# $this->TemplateControl->css('reaction-symbol') %>"><%# $this->Data['symbol'] %></span> <span class="<%# $this->TemplateControl->css('reaction-count') %>"><%# $this->Data['count'] %></span></span>
		</prop:ItemTemplate>
	</com:TRepeater>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
</div>
