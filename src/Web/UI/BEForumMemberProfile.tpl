<div class="<%= $this->wrapperCss('profile') %>">
	<div class="<%= $this->css('profile-header') %>">
		<com:Belisoful\Forum\Web\UI\BEForumMemberCard ID="Card" />
		<dl class="<%= $this->css('profile-details') %>">
			<com:TRepeater ID="Details">
				<prop:ItemTemplate><dt><%# $this->Data['label'] %></dt><dd><%# $this->Data['value'] %></dd></prop:ItemTemplate>
			</com:TRepeater>
		</dl>
		<div class="<%= $this->css('profile-actions') %>">
			<com:TLinkButton ID="EditButton" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Edit profile') %> OnClick="editClicked" CausesValidation="false" />
		</div>
	</div>
	<com:TLabel ID="Error" CssClass=<%= $this->css('error') %> Visible="false" />
	<div class="<%= $this->css('profile-bio') %>"><com:TLiteral ID="Bio" /></div>
	<div class="<%= $this->css('profile-signature') %>"><com:TLiteral ID="Signature" /></div>
	<com:Belisoful\Forum\Web\UI\BEForumProfileEditor ID="Editor" Visible="false" />
	<com:TPanel ID="ModerationPanel" CssClass=<%= $this->css('profile-moderation') %>>
		<h3><%= $this->te('Moderation') %></h3>
		<div class="<%= $this->css('profile-moderation-row') %>">
			<com:TTextBox ID="BanUntil" TextMode="DatetimeLocal" CssClass=<%= $this->css('input', 'inline') %> ToolTip=<%= $this->te('Ban until (UTC, empty for permanent)') %> />
			<com:TTextBox ID="BanReason" CssClass=<%= $this->css('input', 'inline') %> Attributes.placeholder=<%= $this->te('Reason') %> MaxLength="500" />
			<com:TLinkButton ID="Ban" CssClass=<%= $this->css('button', 'danger') %> Text=<%= $this->te('Ban') %> OnClick="banClicked" CausesValidation="false" />
			<com:TLinkButton ID="Unban" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Lift ban') %> OnClick="unbanClicked" CausesValidation="false" />
		</div>
		<div class="<%= $this->css('profile-moderation-row') %>">
			<com:TTextBox ID="WarnReason" CssClass=<%= $this->css('input', 'inline') %> Attributes.placeholder=<%= $this->te('Warning reason') %> MaxLength="500" />
			<com:TLinkButton ID="Warn" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Warn') %> OnClick="warnClicked" CausesValidation="false" />
		</div>
		<com:TPanel ID="BadgePanel" CssClass=<%= $this->css('profile-moderation-row') %>>
			<com:TDropDownList ID="BadgeList" CssClass=<%= $this->css('select') %> />
			<com:TLinkButton ID="Award" CssClass=<%= $this->css('button', 'secondary') %> Text=<%= $this->te('Award badge') %> OnClick="awardClicked" CausesValidation="false" />
		</com:TPanel>
	</com:TPanel>
	<section class="<%= $this->css('profile-threads') %>">
		<h3><%= $this->te('Threads') %></h3>
		<com:Belisoful\Forum\Web\UI\BEForumThreadList ID="Threads" PageSize="10" />
	</section>
	<com:TPanel ID="RecentPosts" CssClass=<%= $this->css('profile-posts') %>>
		<h3><%= $this->te('Recent posts') %></h3>
		<com:TRepeater ID="Posts" ItemRenderer="Belisoful\Forum\Web\UI\BEForumPostView">
			<prop:EmptyTemplate><p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('No posts yet.') %></p></prop:EmptyTemplate>
		</com:TRepeater>
	</com:TPanel>
</div>
