<section class="<%= $this->wrapperCss('poll') %>">
	<h3 class="<%= $this->css('poll-question') %>"><%= $this->getPoll() ? $this->e((string) $this->getPoll()->question) : '' %></h3>
	<div class="<%= $this->css('poll-status') %>"><%= $this->getStatusHtml() %></div>
	<com:TPanel ID="VoteForm" CssClass=<%= $this->css('poll-form') %>>
		<com:TRadioButtonList ID="SingleChoice" CssClass=<%= $this->css('poll-options') %> RepeatLayout="Flow" />
		<com:TCheckBoxList ID="MultiChoice" CssClass=<%= $this->css('poll-options') %> RepeatLayout="Flow" />
		<com:TButton ID="Vote" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Vote') %> OnClick="voteClicked" CausesValidation="false" />
	</com:TPanel>
	<com:TRepeater ID="Results">
		<prop:HeaderTemplate><ul class="<%= $this->TemplateControl->css('poll-results') %>"></prop:HeaderTemplate>
		<prop:ItemTemplate>
			<li class="<%# $this->TemplateControl->css('poll-result', $this->Data['mine'] ? 'mine' : null) %>">
				<span class="<%# $this->TemplateControl->css('poll-result-label') %>"><%# $this->Data['label'] %></span>
				<span class="<%# $this->TemplateControl->css('poll-result-bar') %>"><span class="<%# $this->TemplateControl->css('poll-result-fill') %>" style="width:<%# $this->Data['percent'] %>%"></span></span>
				<span class="<%# $this->TemplateControl->css('poll-result-count') %>"><%# $this->Data['votes'] %> (<%# $this->Data['percent'] %>%)</span>
			</li>
		</prop:ItemTemplate>
		<prop:FooterTemplate></ul></prop:FooterTemplate>
	</com:TRepeater>
	<com:TLinkButton ID="ToggleClosed" CssClass=<%= $this->css('action') %> OnClick="toggleClosedClicked" CausesValidation="false" />
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
</section>
