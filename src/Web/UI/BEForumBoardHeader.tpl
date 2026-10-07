<header class="<%= $this->wrapperCss('board-header') %>">
	<div class="<%= $this->css('board-header-main') %>">
		<h1 class="<%= $this->css('board-header-title') %>"><%= $this->e((string) $this->getBoard()->name) %></h1>
		<div class="<%= $this->css('board-header-description') %>"><%= $this->getDescriptionHtml() %></div>
		<div class="<%= $this->css('board-header-moderators') %>"><%= $this->getModeratorsHtml() %></div>
		<com:TRepeater ID="SubBoards">
			<prop:HeaderTemplate><ul class="<%= $this->TemplateControl->css('sub-boards') %>"><li class="<%= $this->TemplateControl->css('sub-boards-label') %>"><%= $this->TemplateControl->te('Sub boards') %>:</li></prop:HeaderTemplate>
			<prop:ItemTemplate><li><a class="<%# $this->TemplateControl->css('sub-board') %>" href="<%# $this->Data['url'] %>"><%# $this->Data['name'] %></a></li></prop:ItemTemplate>
			<prop:FooterTemplate></ul></prop:FooterTemplate>
		</com:TRepeater>
	</div>
	<div class="<%= $this->css('board-header-actions') %>">
		<com:Belisoful\Forum\Web\UI\BEForumSubscribeButton ID="Subscribe" />
		<com:TLinkButton ID="MarkRead" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Mark all read') %> OnClick="markReadClicked" CausesValidation="false" />
		<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	</div>
</header>
