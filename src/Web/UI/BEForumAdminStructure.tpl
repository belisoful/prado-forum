<div class="<%= $this->wrapperCss('admin') %>">
	<h1 class="<%= $this->css('admin-title') %>"><%= $this->te('Forum structure') %></h1>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<div class="<%= $this->css('admin-columns') %>">
		<section class="<%= $this->css('admin-list') %>">
			<com:TRepeater ID="Categories" OnItemCommand="categoryCommand" OnItemDataBound="categoryDataBound" OnItemCreated="categoryItemCreated">
				<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No categories yet. Create one with the form.') %></p></prop:EmptyTemplate>
				<prop:ItemTemplate>
					<div class="<%# $this->TemplateControl->css('admin-category', $this->Data['hidden'] ? 'hidden' : null) %>">
						<div class="<%# $this->TemplateControl->css('admin-row') %>">
							<strong><%# $this->Data['name'] %></strong>
							<span class="<%# $this->TemplateControl->css('admin-row-actions') %>">
								<com:TLinkButton Text="&#9650;" CommandName="up" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" ToolTip=<%# $this->TemplateControl->t('Move up') %> />
								<com:TLinkButton Text="&#9660;" CommandName="down" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" ToolTip=<%# $this->TemplateControl->t('Move down') %> />
								<com:TLinkButton Text=<%# $this->TemplateControl->te('Edit') %> CommandName="edit" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
								<com:TLinkButton CssClass=<%# $this->TemplateControl->css('action', 'danger') %> Text=<%# $this->TemplateControl->te('Delete') %> CommandName="delete" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" Attributes.onclick=<%# $this->TemplateControl->confirmScript('Delete this category?') %> />
							</span>
						</div>
						<com:TRepeater ID="Boards">
							<prop:ItemTemplate>
								<div class="<%# $this->TemplateControl->TemplateControl->css('admin-board', $this->Data['parent'] ? 'child' : null) %>">
									<div class="<%# $this->TemplateControl->TemplateControl->css('admin-row') %>">
										<a href="<%# $this->Data['url'] %>"><%# $this->Data['name'] %></a>
										<span class="<%# $this->TemplateControl->TemplateControl->css('muted') %>"><%# $this->Data['flags'] %> &middot; <%# $this->Data['threads'] %> <%# $this->TemplateControl->TemplateControl->te('threads') %></span>
										<span class="<%# $this->TemplateControl->TemplateControl->css('admin-row-actions') %>">
											<com:TLinkButton Text="&#9650;" CommandName="up" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
											<com:TLinkButton Text="&#9660;" CommandName="down" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
											<com:TLinkButton Text=<%# $this->TemplateControl->TemplateControl->te('Edit') %> CommandName="edit" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
											<com:TLinkButton CssClass=<%# $this->TemplateControl->TemplateControl->css('action', 'danger') %> Text=<%# $this->TemplateControl->TemplateControl->te('Delete') %> CommandName="delete" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" Attributes.onclick=<%# $this->TemplateControl->TemplateControl->confirmScript('Delete this board?') %> />
										</span>
									</div>
									<div class="<%# $this->TemplateControl->TemplateControl->css('admin-moderators') %>">
										<%# $this->TemplateControl->TemplateControl->te('Moderators') %>:
										<com:TRepeater ID="Moderators">
											<prop:ItemTemplate><span class="<%# $this->TemplateControl->TemplateControl->TemplateControl->css('admin-moderator') %>"><%# $this->Data['name'] %> <com:TLinkButton Text="&times;" CommandName="removemod" CommandParameter=<%# $this->Data['board'] . ':' . $this->Data['id'] %> CausesValidation="false" /></span></prop:ItemTemplate>
										</com:TRepeater>
										<com:TTextBox ID="NewModerator" CssClass=<%# $this->TemplateControl->TemplateControl->css('input', 'inline') %> Attributes.placeholder=<%# $this->TemplateControl->TemplateControl->t('username') %> />
										<com:TLinkButton Text=<%# $this->TemplateControl->TemplateControl->te('Add moderator') %> CommandName="addmod" CommandParameter=<%# $this->Data['id'] %> CausesValidation="false" />
									</div>
								</div>
							</prop:ItemTemplate>
						</com:TRepeater>
					</div>
				</prop:ItemTemplate>
			</com:TRepeater>
		</section>
		<section class="<%= $this->css('admin-forms') %>">
			<com:TPanel CssClass=<%= $this->css('editor-form') %> DefaultButton="SaveCategory">
				<h3><com:TLiteral ID="CategoryFormTitle" /></h3>
				<label for="<%= $this->CategoryName->getClientID() %>"><%= $this->te('Name') %></label>
				<com:TTextBox ID="CategoryName" CssClass=<%= $this->css('input') %> MaxLength="120" />
				<label for="<%= $this->CategoryDescription->getClientID() %>"><%= $this->te('Description') %></label>
				<com:TTextBox ID="CategoryDescription" TextMode="MultiLine" Rows="3" CssClass=<%= $this->css('input') %> />
				<com:TCheckBox ID="CategoryHidden" Text=<%= $this->te('Hidden') %> CssClass=<%= $this->css('checkbox') %> />
				<div class="<%= $this->css('editor-actions') %>">
					<com:TButton ID="SaveCategory" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Save category') %> OnClick="saveCategoryClicked" CausesValidation="false" />
					<com:TLinkButton ID="CancelCategory" CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('Cancel') %> OnClick="cancelCategoryClicked" CausesValidation="false" />
				</div>
			</com:TPanel>
			<com:TPanel CssClass=<%= $this->css('editor-form') %> DefaultButton="SaveBoard">
				<h3><com:TLiteral ID="BoardFormTitle" /></h3>
				<label for="<%= $this->BoardName->getClientID() %>"><%= $this->te('Name') %></label>
				<com:TTextBox ID="BoardName" CssClass=<%= $this->css('input') %> MaxLength="120" />
				<label for="<%= $this->BoardDescription->getClientID() %>"><%= $this->te('Description') %></label>
				<com:TTextBox ID="BoardDescription" TextMode="MultiLine" Rows="3" CssClass=<%= $this->css('input') %> />
				<label for="<%= $this->BoardCategory->getClientID() %>"><%= $this->te('Category') %></label>
				<com:TDropDownList ID="BoardCategory" CssClass=<%= $this->css('select') %> />
				<label for="<%= $this->BoardParent->getClientID() %>"><%= $this->te('Parent board') %></label>
				<com:TDropDownList ID="BoardParent" CssClass=<%= $this->css('select') %> />
				<com:TCheckBox ID="BoardLocked" Text=<%= $this->te('Locked (no new threads)') %> CssClass=<%= $this->css('checkbox') %> />
				<com:TCheckBox ID="BoardHidden" Text=<%= $this->te('Hidden (moderators only)') %> CssClass=<%= $this->css('checkbox') %> />
				<com:TCheckBox ID="BoardPrivate" Text=<%= $this->te('Private (members only)') %> CssClass=<%= $this->css('checkbox') %> />
				<div class="<%= $this->css('editor-actions') %>">
					<com:TButton ID="SaveBoard" CssClass=<%= $this->css('button', 'primary') %> Text=<%= $this->te('Save board') %> OnClick="saveBoardClicked" CausesValidation="false" />
					<com:TLinkButton ID="CancelBoard" CssClass=<%= $this->css('button', 'link') %> Text=<%= $this->te('Cancel') %> OnClick="cancelBoardClicked" CausesValidation="false" />
				</div>
			</com:TPanel>
		</section>
	</div>
</div>
