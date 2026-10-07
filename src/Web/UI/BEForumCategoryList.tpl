<div class="<%= $this->wrapperCss('index') %>">
	<com:TRepeater ID="Categories" OnItemDataBound="categoryDataBound">
		<prop:EmptyTemplate>
			<p class="<%= $this->TemplateControl->css('empty') %>"><%= $this->TemplateControl->te('There are no boards yet.') %></p>
		</prop:EmptyTemplate>
		<prop:ItemTemplate>
			<section class="<%# $this->TemplateControl->css('category') %>" id="category-<%# $this->Data['id'] %>">
				<h2 class="<%# $this->TemplateControl->css('category-title') %>"><%# $this->Data['name'] %></h2>
				<com:TLiteral Text=<%# $this->Data['description'] !== '' ? '<div class="' . $this->TemplateControl->css('category-description') . '">' . $this->Data['description'] . '</div>' : '' %> />
				<table class="<%# $this->TemplateControl->css('board-table') %>">
					<thead>
						<tr>
							<th scope="col"><%# $this->TemplateControl->te('Board') %></th>
							<th scope="col" class="<%# $this->TemplateControl->css('col-count') %>"><%# $this->TemplateControl->te('Threads') %></th>
							<th scope="col" class="<%# $this->TemplateControl->css('col-count') %>"><%# $this->TemplateControl->te('Posts') %></th>
							<th scope="col" class="<%# $this->TemplateControl->css('col-last') %>"><%# $this->TemplateControl->te('Last post') %></th>
						</tr>
					</thead>
					<tbody>
						<com:TRepeater ID="Boards" ItemRenderer="Belisoful\Forum\Web\UI\BEForumBoardRow" />
					</tbody>
				</table>
			</section>
		</prop:ItemTemplate>
	</com:TRepeater>
</div>
