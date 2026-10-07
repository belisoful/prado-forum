<div class="<%= $this->wrapperCss('tools') %>">
	<div class="<%= $this->css('tools-actions') %>">
		<com:TLinkButton ID="Edit" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Edit thread') %> OnClick="editClicked" CausesValidation="false" />
		<com:TLinkButton ID="ToggleLock" CssClass=<%= $this->css('action') %> OnClick="toggleLockClicked" CausesValidation="false" />
		<com:TLinkButton ID="TogglePin" CssClass=<%= $this->css('action') %> OnClick="togglePinClicked" CausesValidation="false" />
		<com:TTextBox ID="PinUntil" TextMode="DatetimeLocal" CssClass=<%= $this->css('input', 'inline') %> ToolTip=<%= $this->te('Pin until (UTC, optional)') %> />
		<com:TLinkButton ID="Approve" CssClass=<%= $this->css('action', 'primary') %> Text=<%= $this->te('Approve') %> OnClick="approveClicked" CausesValidation="false" />
		<com:TLinkButton ID="Delete" CssClass=<%= $this->css('action', 'danger') %> Text=<%= $this->te('Delete thread') %> OnClick="deleteClicked" CausesValidation="false" Attributes.onclick=<%= $this->confirmScript('Delete this thread?') %> />
		<com:TLinkButton ID="Restore" CssClass=<%= $this->css('action') %> Text=<%= $this->te('Restore thread') %> OnClick="restoreClicked" CausesValidation="false" />
		<com:TLinkButton ID="Purge" CssClass=<%= $this->css('action', 'danger') %> Text=<%= $this->te('Delete permanently') %> OnClick="purgeClicked" CausesValidation="false" Attributes.onclick=<%= $this->confirmScript('Permanently delete this thread and all its posts?') %> />
	</div>
	<com:TPanel ID="MovePanel" CssClass=<%= $this->css('tools-move') %>>
		<com:TDropDownList ID="MoveTarget" CssClass=<%= $this->css('select') %> />
		<com:TLinkButton CssClass=<%= $this->css('action') %> Text=<%= $this->te('Move') %> OnClick="moveClicked" CausesValidation="false" />
	</com:TPanel>
	<com:TPanel ID="EditPanel" CssClass=<%= $this->css('tools-edit') %> Visible="false" DefaultButton="Save">
		<label for="<%= $this->Title->getClientID() %>"><%= $this->te('Title') %></label>
		<com:TTextBox ID="Title" CssClass=<%= $this->css('input') %> MaxLength=<%= $this->getForum()->getMaxTitleLength() %> />
		<label for="<%= $this->Type->getClientID() %>"><%= $this->te('Type') %></label>
		<com:TDropDownList ID="Type" CssClass=<%= $this->css('select') %> />
		<com:TLabel ID="TagsLabel" ForControl="Tags" Text=<%= $this->te('Tags (comma separated)') %> />
		<com:TTextBox ID="Tags" CssClass=<%= $this->css('input') %> />
		<com:TButton ID="Save" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Save') %> OnClick="saveClicked" CausesValidation="false" />
		<com:TLinkButton CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('Cancel') %> OnClick="cancelEditClicked" CausesValidation="false" />
	</com:TPanel>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
</div>
