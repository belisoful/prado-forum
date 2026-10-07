<div class="<%= $this->wrapperCss('post-list') %>">
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
	<com:TRepeater ID="Posts" ItemRenderer="Belisoful\Forum\Web\UI\BEForumPostView" OnItemCommand="itemCommand">
		<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No posts.') %></p></prop:EmptyTemplate>
	</com:TRepeater>
</div>
