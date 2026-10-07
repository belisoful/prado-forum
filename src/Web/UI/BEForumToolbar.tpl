<header class="<%= $this->wrapperCss('toolbar') %>">
	<a class="<%= $this->css('toolbar-title') %>" href="<%= $this->e($this->getUrls()->index()) %>"><%= $this->e($this->getForum()->getTitle()) %></a>
	<nav class="<%= $this->css('toolbar-nav') %>" aria-label="<%= $this->te('Forum navigation') %>">
		<ul class="<%= $this->css('toolbar-links') %>">
			<com:TRepeater ID="Links">
				<prop:ItemTemplate>
					<li class="<%# $this->TemplateControl->css('toolbar-link', $this->Data['css']) %>">
						<a href="<%# $this->Data['url'] %>"><%# $this->Data['label'] %><com:TLiteral Text=<%# $this->Data['badge'] !== '' ? ' <span class="' . $this->TemplateControl->css('badge') . '">' . $this->Data['badge'] . '</span>' : '' %> /></a>
					</li>
				</prop:ItemTemplate>
			</com:TRepeater>
		</ul>
	</nav>
	<com:Belisoful\Forum\Web\UI\BEForumSearchBox ID="Search" />
</header>
