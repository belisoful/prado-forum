<section class="<%= $this->wrapperCss('recent-posts') %>">
	<h3 class="<%= $this->css('panel-title') %>"><%= $this->te('Recent posts') %></h3>
	<ul class="<%= $this->css('recent-posts-list') %>">
		<com:TRepeater ID="Rows">
			<prop:EmptyTemplate><li class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No posts yet.') %></li></prop:EmptyTemplate>
			<prop:ItemTemplate>
				<li class="<%# $this->TemplateControl->css('recent-post') %>">
					<a class="<%# $this->TemplateControl->css('recent-post-title') %>" href="<%# $this->Data['url'] %>"><%# $this->Data['title'] %></a>
					<div class="<%# $this->TemplateControl->css('recent-post-excerpt') %>"><%# $this->Data['excerpt'] %></div>
					<div class="<%# $this->TemplateControl->css('recent-post-meta') %>"><%# $this->Data['author'] %> <%# $this->Data['time'] %></div>
				</li>
			</prop:ItemTemplate>
		</com:TRepeater>
	</ul>
</section>
