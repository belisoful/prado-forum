<div class="<%= $this->wrapperCss('search') %>">
	<com:TPanel CssClass=<%= $this->css('search-form') %> DefaultButton="Go">
		<com:TTextBox ID="Query" CssClass=<%= $this->css('input') %> TextMode="Search" ToolTip=<%= $this->te('Search terms') %> />
		<com:TDropDownList ID="Board" CssClass=<%= $this->css('select') %> />
		<com:TRadioButtonList ID="Scope" CssClass=<%= $this->css('radio-list') %> RepeatLayout="Flow" RepeatDirection="Horizontal" />
		<com:TButton ID="Go" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Search') %> OnClick="searchClicked" CausesValidation="false" />
	</com:TPanel>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<com:TLabel ID="Summary" CssClass=<%= $this->css('search-summary') %> />
	<com:TPanel ID="PostResults" CssClass=<%= $this->css('search-results') %>>
		<com:TRepeater ID="PostRows" ItemRenderer="Belisoful\Forum\Web\UI\BEForumPostView">
			<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No posts matched your search.') %></p></prop:EmptyTemplate>
		</com:TRepeater>
	</com:TPanel>
	<com:TPanel ID="ThreadResults" CssClass=<%= $this->css('search-results') %>>
		<table class="<%= $this->css('thread-table') %>">
			<thead><tr><th scope="col"><%= $this->te('Thread') %></th><th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Replies') %></th><th scope="col" class="<%= $this->css('col-count') %>"><%= $this->te('Views') %></th><th scope="col" class="<%= $this->css('col-last') %>"><%= $this->te('Last post') %></th></tr></thead>
			<tbody>
				<com:TRepeater ID="ThreadRows" ItemRenderer="Belisoful\Forum\Web\UI\BEForumThreadRow">
					<prop:EmptyTemplate><tr><td colspan="4" class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No threads matched your search.') %></td></tr></prop:EmptyTemplate>
				</com:TRepeater>
			</tbody>
		</table>
	</com:TPanel>
	<com:Belisoful\Forum\Web\UI\BEForumPager ID="Pager" />
</div>
