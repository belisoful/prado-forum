<div class="<%= $this->wrapperCss('bookmarks') %>">
	<com:TRepeater ID="Rows" ItemRenderer="Belisoful\Forum\Web\UI\BEForumPostView">
		<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('You have no bookmarks.') %></p></prop:EmptyTemplate>
	</com:TRepeater>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
</div>
