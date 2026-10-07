<section class="<%= $this->wrapperCss('profile-editor') %>">
	<h3 class="<%= $this->css('editor-title') %>"><%= $this->te('Edit profile') %></h3>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<com:TLabel ID="Saved" CssClass=<%= $this->css('success') %> Text=<%= $this->te('Profile saved.') %> Visible="false" />
	<com:TPanel ID="Form" CssClass=<%= $this->css('editor-form') %> DefaultButton="Save">
		<label for="<%= $this->DisplayName->getClientID() %>"><%= $this->te('Display name') %></label>
		<com:TTextBox ID="DisplayName" CssClass=<%= $this->css('input') %> MaxLength="120" />
		<label for="<%= $this->Email->getClientID() %>"><%= $this->te('E-mail (for the avatar, never shown)') %></label>
		<com:TTextBox ID="Email" CssClass=<%= $this->css('input') %> TextMode="Email" MaxLength="254" />
		<label for="<%= $this->AvatarUrl->getClientID() %>"><%= $this->te('Avatar URL') %></label>
		<com:TTextBox ID="AvatarUrl" CssClass=<%= $this->css('input') %> TextMode="Url" MaxLength="500" />
		<label for="<%= $this->Location->getClientID() %>"><%= $this->te('Location') %></label>
		<com:TTextBox ID="Location" CssClass=<%= $this->css('input') %> MaxLength="120" />
		<label for="<%= $this->Website->getClientID() %>"><%= $this->te('Web site') %></label>
		<com:TTextBox ID="Website" CssClass=<%= $this->css('input') %> TextMode="Url" MaxLength="500" />
		<label for="<%= $this->Timezone->getClientID() %>"><%= $this->te('Timezone') %></label>
		<com:TDropDownList ID="Timezone" CssClass=<%= $this->css('select') %> />
		<label for="<%= $this->Bio->getClientID() %>"><%= $this->te('About me') %></label>
		<com:TTextBox ID="Bio" TextMode="MultiLine" Rows="5" CssClass=<%= $this->css('input') %> />
		<label for="<%= $this->Signature->getClientID() %>"><%= $this->te('Signature') %></label>
		<com:TTextBox ID="Signature" TextMode="MultiLine" Rows="3" CssClass=<%= $this->css('input') %> />
		<fieldset class="<%= $this->css('fieldset') %>">
			<legend><%= $this->te('Notifications') %></legend>
			<com:TCheckBoxList ID="Notifications" CssClass=<%= $this->css('checkbox-list') %> RepeatLayout="Flow" />
		</fieldset>
		<div class="<%= $this->css('editor-actions') %>">
			<com:TButton ID="Save" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Save profile') %> OnClick="saveClicked" CausesValidation="false" />
		</div>
	</com:TPanel>
</section>
