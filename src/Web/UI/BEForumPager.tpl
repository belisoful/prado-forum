<nav class="<%= $this->wrapperCss('pager') %>" aria-label="<%= $this->te('Pages') %>">
	<span class="<%= $this->css('pager-summary') %>"><%= $this->getSummary() %></span>
	<ul class="<%= $this->css('pager-list') %>">
		<com:TRepeater ID="Links">
			<prop:ItemTemplate>
				<li class="<%# $this->Data['current'] ? $this->TemplateControl->css('pager-item', 'current') : ($this->Data['gap'] ? $this->TemplateControl->css('pager-item', 'gap') : $this->TemplateControl->css('pager-item')) %>">
					<com:TLiteral Text=<%# $this->Data['gap'] || $this->Data['current'] ? '<span aria-current="' . ($this->Data['current'] ? 'page' : 'false') . '">' . $this->Data['label'] . '</span>' : '<a href="' . $this->Data['url'] . '"' . ($this->Data['rel'] ? ' rel="' . $this->Data['rel'] . '"' : '') . '>' . $this->Data['label'] . '</a>' %> />
				</li>
			</prop:ItemTemplate>
		</com:TRepeater>
	</ul>
</nav>
