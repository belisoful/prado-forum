<div class="<%= $this->wrapperCss('thread-view') %>">
	<com:Belisoful\Forum\Web\UI\BEForumBreadcrumbs ID="Crumbs" />
	<header class="<%= $this->css('thread-header') %>">
		<h1 class="<%= $this->css('thread-header-title') %>"><%= $this->getFlagsHtml() %> <%= $this->e((string) $this->getThread()->title) %></h1>
		<div class="<%= $this->css('thread-header-meta') %>">
			<%= $this->getAuthorHtml() %>
			<com:TRepeater ID="Tags">
				<prop:HeaderTemplate><span class="<%= $this->TemplateControl->css('tags') %>"></prop:HeaderTemplate>
				<prop:ItemTemplate><a class="<%# $this->TemplateControl->css('tag') %>" href="<%# $this->Data['url'] %>"><%# $this->Data['name'] %></a></prop:ItemTemplate>
				<prop:FooterTemplate></span></prop:FooterTemplate>
			</com:TRepeater>
			<com:THyperLink ID="AcceptedLink" CssClass=<%= $this->css('thread-accepted') %> Text=<%= $this->te('Jump to accepted answer') %> Visible="false" />
		</div>
		<div class="<%= $this->css('thread-header-actions') %>">
			<com:Belisoful\Forum\Web\UI\BEForumSubscribeButton ID="Subscribe" />
		</div>
		<com:Belisoful\Forum\Web\UI\BEForumModerationTools ID="Tools" />
	</header>
	<com:Belisoful\Forum\Web\UI\BEForumPoll ID="PollView" />
	<com:Belisoful\Forum\Web\UI\BEForumPostList ID="Posts" OnPostCommand="postCommand" />
	<com:Belisoful\Forum\Web\UI\BEForumPostEditor ID="Editor" />
</div>
