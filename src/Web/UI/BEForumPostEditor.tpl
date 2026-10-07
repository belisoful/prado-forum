<section class="<%= $this->wrapperCss('editor') %>" id="<%= $this->getClientID() %>_anchor">
	<h3 class="<%= $this->css('editor-title') %>"><%= $this->te($this->getIsEditMode() ? 'Edit post' : 'Reply') %></h3>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<com:TPanel ID="Form" CssClass=<%= $this->css('editor-form') %> DefaultButton="Submit">
		<div class="<%= $this->css('editor-reply-to') %>">
			<com:TLabel ID="ReplyTo" Visible="false" />
			<com:TLinkButton ID="ClearReplyTo" CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('clear') %> OnClick="clearReplyToClicked" CausesValidation="false" Visible="false" />
		</div>
		<com:TLabel ID="GuestLabel" ForControl="GuestName" Text=<%= $this->te('Your name') %> />
		<com:TTextBox ID="GuestName" CssClass=<%= $this->css('input') %> MaxLength="120" />
		<label for="<%= $this->Content->getClientID() %>"><%= $this->te('Message') %></label>
		<com:TTextBox ID="Content" TextMode="MultiLine" Rows="10" CssClass=<%= $this->css('input', 'content') %> />
		<com:TRequiredFieldValidator ControlToValidate="Content" ValidationGroup=<%= $this->getUniqueID() %> Display="Dynamic" CssClass=<%= $this->css('validator') %> ErrorMessage=<%= $this->te('Please write a message.') %> />
		<com:TDropDownList ID="Format" CssClass=<%= $this->css('select') %> />
		<com:TLabel ID="UploadLabel" ForControl="Upload" Text=<%= $this->te('Attachments') %> />
		<com:TFileUpload ID="Upload" Multiple="true" CssClass=<%= $this->css('upload') %> />
		<com:TLabel ID="ReasonLabel" ForControl="Reason" Text=<%= $this->te('Edit reason (optional)') %> />
		<com:TTextBox ID="Reason" CssClass=<%= $this->css('input') %> MaxLength="255" />
		<com:TCheckBox ID="Subscribe" Text=<%= $this->te('Notify me of replies') %> CssClass=<%= $this->css('checkbox') %> />
		<com:TPanel ID="PreviewPanel" CssClass=<%= $this->css('editor-preview') %> Visible="false">
			<h4><%= $this->te('Preview') %></h4>
			<div class="<%= $this->css('post-content') %>"><com:TLiteral ID="Preview" /></div>
		</com:TPanel>
		<div class="<%= $this->css('editor-actions') %>">
			<com:TButton ID="Submit" CssClass=<%= $this->css('button', 'primary') %> OnClick="submitClicked" ValidationGroup=<%= $this->getUniqueID() %> />
			<com:TButton ID="PreviewButton" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Preview') %> OnClick="previewClicked" CausesValidation="false" />
			<com:THyperLink ID="Cancel" CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('Cancel') %> />
		</div>
	</com:TPanel>
</section>
