<section class="<%= $this->wrapperCss('tag-cloud') %>">
	<h3 class="<%= $this->css('panel-title') %>"><%= $this->te('Tags') %></h3>
	<ul class="<%= $this->css('tag-cloud-list') %>">
		<com:TRepeater ID="Rows">
			<prop:ItemTemplate>
				<li><a class="<%# $this->TemplateControl->css('tag', 'w' . $this->Data['weight']) %>" href="<%# $this->Data['url'] %>" title="<%# $this->Data['count'] %>"><%# $this->Data['name'] %></a></li>
			</prop:ItemTemplate>
		</com:TRepeater>
	</ul>
</section>
