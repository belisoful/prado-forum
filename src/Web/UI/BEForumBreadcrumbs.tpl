<nav class="<%= $this->wrapperCss('breadcrumbs') %>" aria-label="<%= $this->te('Breadcrumb') %>">
	<ol class="<%= $this->css('breadcrumbs-list') %>">
		<com:TRepeater ID="Crumbs">
			<prop:ItemTemplate>
				<li class="<%# $this->TemplateControl->css('breadcrumbs-item', $this->Data['last'] ? 'current' : null) %>">
					<com:TLiteral Text=<%# $this->Data['last'] || $this->Data['url'] === '' ? '<span aria-current="page">' . $this->Data['label'] . '</span>' : '<a href="' . $this->Data['url'] . '">' . $this->Data['label'] . '</a>' %> />
				</li>
			</prop:ItemTemplate>
		</com:TRepeater>
	</ol>
</nav>
