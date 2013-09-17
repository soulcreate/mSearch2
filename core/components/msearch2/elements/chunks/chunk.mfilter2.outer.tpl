<div class="row" id="mse2_mfilter">
	<div class="span3">
		<form action="" method="post" id="mse2_filters">
			[[+filters]]
		</form>

		<div>[[%mse2_filter_total]] <span id="mse2_total">[[+total:default=`0`]]</span></div>
	</div>

	<div class="span9">
		<div class="row">
			<div id="mse2_sort" class="span5">
				[[%mse2_sort]]
				<a href="#" data-sort="resource|publishedon" data-dir="[[+mse2_sort:is=`resource|publishedon:desc`:then=`desc`]]" data-default="desc" class="sort">[[%mse2_sort_publishedon]] <span></span></a>
			</div>

			[[+tpls:notempty=`
			<div id="mse2_tpl" class="span4">
				<a href="#" data-tpl="0" class="[[+tpl0]]">[[%mse2_chunk_default]]</a> /
				<a href="#" data-tpl="1" class="[[+tpl1]]">[[%mse2_chunk_alternate]]</a>
			</div>
			`]]
		</div>

		<div id="mse2_selected_wrapper">
			<div id="mse2_selected">[[%mse2_selected]]:
				<span></span>
			</div>
		</div>

		<div id="mse2_results">
			[[+results]]
		</div>

		<label class="inline">[[%mse2_limit]]<br/>
			<select name="mse_limit" id="mse2_limit">
				<option value="10" [[+limit:is=`10`:then=`selected`]]>10</option>
				<option value="25" [[+limit:is=`25`:then=`selected`]]>25</option>
				<option value="50" [[+limit:is=`50`:then=`selected`]]>50</option>
				<option value="100" [[+limit:is=`100`:then=`selected`]]>100</option>
			</select>
		</label>

		<div class="pagination">
			<ul id="mse2_pagination">
				[[!+page.nav]]
			</ul>
		</div>

	</div>
</div>