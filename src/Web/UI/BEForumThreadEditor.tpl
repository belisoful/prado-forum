<section class="<%= $this->wrapperCss('editor') %>">
	<h2 class="<%= $this->css('editor-title') %>"><%= $this->te('New thread') %></h2>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<com:TPanel ID="Form" CssClass=<%= $this->css('editor-form') %> DefaultButton="Submit">
		<com:TLabel ID="GuestLabel" ForControl="GuestName" Text=<%= $this->te('Your name') %> />
		<com:TTextBox ID="GuestName" CssClass=<%= $this->css('input') %> MaxLength="120" />
		<label for="<%= $this->Title->getClientID() %>"><%= $this->te('Title') %></label>
		<com:TTextBox ID="Title" CssClass=<%= $this->css('input') %> MaxLength=<%= $this->getForum()->getMaxTitleLength() %> />
		<com:TRequiredFieldValidator ControlToValidate="Title" ValidationGroup="beforum-thread" Display="Dynamic" CssClass=<%= $this->css('validator') %> ErrorMessage=<%= $this->te('Please enter a title.') %> />
		<label for="<%= $this->Type->getClientID() %>"><%= $this->te('Type') %></label>
		<com:TDropDownList ID="Type" CssClass=<%= $this->css('select') %> />
		<com:TLabel ID="TagsLabel" ForControl="Tags" Text=<%= $this->te('Tags (comma separated)') %> />
		<com:TTextBox ID="Tags" CssClass=<%= $this->css('input') %> />
		<label for="<%= $this->Content->getClientID() %>"><%= $this->te('Message') %></label>
		<com:TTextBox ID="Content" TextMode="MultiLine" Rows="12" CssClass=<%= $this->css('input', 'content') %> />
		<com:TRequiredFieldValidator ControlToValidate="Content" ValidationGroup="beforum-thread" Display="Dynamic" CssClass=<%= $this->css('validator') %> ErrorMessage=<%= $this->te('Please write a message.') %> />
		<com:TDropDownList ID="Format" CssClass=<%= $this->css('select') %> />
		<com:TLabel ID="UploadLabel" ForControl="Upload" Text=<%= $this->te('Attachments') %> />
		<com:TFileUpload ID="Upload" Multiple="true" CssClass=<%= $this->css('upload') %> />
		<com:TPanel ID="PollPanel" CssClass=<%= $this->css('editor-poll') %>>
			<h3><%= $this->te('Poll (optional)') %></h3>
			<label for="<%= $this->PollQuestion->getClientID() %>"><%= $this->te('Question') %></label>
			<com:TTextBox ID="PollQuestion" CssClass=<%= $this->css('input') %> MaxLength="255" />
			<label for="<%= $this->PollOptions->getClientID() %>"><%= $this->te('Options, one per line') %></label>
			<com:TTextBox ID="PollOptions" TextMode="MultiLine" Rows="4" CssClass=<%= $this->css('input') %> />
			<label for="<%= $this->PollMaxChoices->getClientID() %>"><%= $this->te('Choices per member') %></label>
			<com:TTextBox ID="PollMaxChoices" TextMode="Number" CssClass=<%= $this->css('input', 'short') %> Attributes.min="1" />
			<label for="<%= $this->PollClosesAt->getClientID() %>"><%= $this->te('Closes at (UTC, optional)') %></label>
			<com:TTextBox ID="PollClosesAt" TextMode="DatetimeLocal" CssClass=<%= $this->css('input') %> />
			<com:TCheckBox ID="PollAllowRevote" Text=<%= $this->te('Members may change their vote') %> CssClass=<%= $this->css('checkbox') %> />
		</com:TPanel>
		<com:TCheckBox ID="Subscribe" Text=<%= $this->te('Notify me of replies') %> CssClass=<%= $this->css('checkbox') %> />
		<com:TPanel ID="PreviewPanel" CssClass=<%= $this->css('editor-preview') %> Visible="false">
			<h4><%= $this->te('Preview') %></h4>
			<div class="<%= $this->css('post-content') %>"><com:TLiteral ID="Preview" /></div>
		</com:TPanel>
		<div class="<%= $this->css('editor-actions') %>">
			<com:TButton ID="Submit" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Create thread') %> OnClick="submitClicked" ValidationGroup="beforum-thread" />
			<com:TButton ID="PreviewButton" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Preview') %> OnClick="previewClicked" CausesValidation="false" />
			<com:THyperLink ID="Cancel" CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('Cancel') %> />
		</div>
	</com:TPanel>
</section>
