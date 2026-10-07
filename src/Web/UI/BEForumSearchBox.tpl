<div class="<%= $this->wrapperCss('search-box') %>" role="search">
	<com:TTextBox ID="Query" CssClass=<%= $this->css('search-box-input') %> TextMode="Search" Attributes.placeholder=<%= $this->getPlaceholder() %> ToolTip=<%= $this->getPlaceholder() %> />
	<com:TButton ID="Go" CssClass=<%= $this->css('button', 'small') %> Text=<%= $this->t('Search') %> OnClick="searchClicked" CausesValidation="false" />
</div>
