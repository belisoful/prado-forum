<section class="<%= $this->wrapperCss('statistics') %>">
	<h3 class="<%= $this->css('panel-title') %>"><%= $this->te('Statistics') %></h3>
	<dl class="<%= $this->css('statistics-list') %>">
		<com:TRepeater ID="Rows">
			<prop:ItemTemplate><dt><%# $this->Data['label'] %></dt><dd><%# $this->Data['value'] %></dd></prop:ItemTemplate>
		</com:TRepeater>
	</dl>
</section>
