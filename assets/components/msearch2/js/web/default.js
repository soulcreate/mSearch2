var mSearch2 = {
	options: {
		wrapper: '#mse2_mfilter'
		,filters: '#mse2_filters'
		,results: '#mse2_results'
		,total: '#mse2_total'
		,pagination: '#mse2_pagination'
		,sort: '#mse2_sort'
		,limit: '#mse2_limit'
		,slider: '.mse2_number_slider'
		,selected: '#mse2_selected'

		,pagination_link: '#mse2_pagination a'
		,sort_link: '#mse2_sort a'
		,tpl_link: '#mse2_tpl a'
		,selected_tpl: '<a href="#" data-id="[[+id]]" class="mse2_selected_link"><em>[[+title]]</em><sup>x</sup></a>'

		,active_class: 'active'
		,disabled_class: 'disabled'
		,prefix: 'mse2_'
		,suggestion: 'sup' // inside filter item, e.g. #mse2_filters
	}
	,sliders: {}
	,initialize: function(selector) {
		var elements = ['filters','results','pagination','total','sort','selected','limit'];
		for (var i in elements) {
			if (elements.hasOwnProperty(i)) {
				var elem = elements[i];
				this[elem] = $(selector).find(this.options[elem]);
				if (!this[elem].length) {
					//console.log('Error: could not initialize element "' + elem + '" with selector "' + this.options[elem] + '".');
				}
			}
		}

		this.handlePagination();
		this.handleSort();
		this.handleTpl();
		this.handleSlider();
		this.handleLimit();

		$(document).on('submit', this.options.filters, function(e) {
			mSearch2Config.page = '';
			mSearch2.load();
			return false;
		});

		$(document).on('change', this.options.filters, function(e) {
			return $(this).submit();
		});

		if (this.selected) {
			$(document).on('change', this.options.filters + ' input[type="checkbox"]', function(e) {
				mSearch2.handleSelected($(this));
			});

			this.filters.find('input[type="checkbox"]:checked').each(function(e) {
				mSearch2.handleSelected($(this));
			});

			$(document).on('click', this.options.selected + ' a', function(e) {
				var id = $(this).data('id').replace(mSearch2Config.filter_delimeter, "\\" + mSearch2Config.filter_delimeter);
				$('#' + id).trigger('click');
				return false;
			});
		}

		mSearch2.setTotal(this.total.text());
		return true;
	}


	,handlePagination: function() {
		$(document).on('click', this.options.pagination_link, function(e) {
			if (!$(this).hasClass(mSearch2.options.active_class)) {
				$(mSearch2.options.pagination).removeClass(mSearch2.options.active_class);
				$(this).addClass(mSearch2.options.active_class);

				var tmp = $(this).prop('href').match(/page[=|\/](\d+)/);
				var page = tmp && tmp[1] ? Number(tmp[1]) : 1;
				mSearch2Config.page = (page != mSearch2Config.start_page) ? page : '';

				mSearch2.load('', function() {
					$('html, body').animate({
						scrollTop: $(mSearch2.options.wrapper).position().top || 0
					}, 0);
				});
			}

			return false;
		});
	}

	,handleSort: function() {
		var params = this.Hash.get();
		if (params.sort) {
			var sorts = params.sort.split(mSearch2Config.values_delimeter);
			for (var i = 0; i < sorts.length; i++) {
				var tmp = sorts[i].split(mSearch2Config.method_delimeter);
				if (tmp[0] && tmp[1]) {
					$(this.options.sort_link +'[data-sort="' + tmp[0] + '"').data('dir', tmp[1]).attr('data-dir', tmp[1]).addClass(this.options.active_class);;
				}
			}
		}

		$(document).on('click', this.options.sort_link, function(e) {
			$(mSearch2.options.sort_link).removeClass(mSearch2.options.active_class);
			$(this).addClass(mSearch2.options.active_class);
			var dir;
			if ($(this).data('dir').length == 0) {
				dir = $(this).data('default');
			}
			else {
				dir = $(this).data('dir') == 'desc'
					? 'asc'
					: 'desc';
			}
			$(mSearch2.options.sort_link).data('dir', '').attr('data-dir', '');
			$(this).data('dir', dir).attr('data-dir', dir);

			var sort = $(this).data('sort');
			if (dir) {
				sort += mSearch2Config.method_delimeter + dir;
			}
			mSearch2Config.sort = (sort != mSearch2Config.start_sort) ? sort : '';
			mSearch2.load();

			return false;
		});
	}

	,handleTpl: function() {
		$(document).on('click', this.options.tpl_link, function(e) {
			if (!$(this).hasClass(mSearch2.options.active_class)) {
				$(mSearch2.options.tpl_link).removeClass(mSearch2.options.active_class);
				$(this).addClass(mSearch2.options.active_class);

				var tpl = $(this).data('tpl');
				mSearch2Config.tpl = (tpl != mSearch2Config.start_tpl && tpl != 0) ? tpl : '';

				mSearch2.load();
			}

			return false;
		});
	}

	,handleSlider: function() {
		if (!$(this.options.slider).length) {
			return false;
		}
		else if (!$.ui || !$.ui.slider) {
			mSearch2.loadJQUI(mSearch2.handleSlider);
		}
		$(this.options.slider).each(function() {
			var fieldset = $(this).parents('fieldset');
			var imin = fieldset.find('input:first');
			var imax = fieldset.find('input:last');
			var vmin = Number(imin.val());
			var vmax = Number(imax.val());
			var $this = $(this);

			$this.slider({
				min: vmin
				,max: vmax
				,values: [vmin, vmax]
				,range: true
				,stop: function(event, ui) {
					imin.val($this.slider('values',0));
					imax.val($this.slider('values',1));
					imin.trigger('change');
				},
				slide: function(event, ui){
					imin.val($this.slider('values',0));
					imax.val($this.slider('values',1));
				}
			});

			var name = imin.prop('name');
			var values = mSearch2.Hash.get();
			if (values[name]) {
				var tmp = values[name].split(mSearch2Config.values_delimeter);
				$this.slider('values', 0, tmp[0]);
				$this.slider('values', 1, tmp[1]);
				imin.val(tmp[0]);
				imax.val(tmp[1]);
			}

			imin.attr('readonly', true);
			imax.attr('readonly', true);
			mSearch2.sliders[imin.prop('name')] = [vmin,vmax];
		});
		return true;
	}

	,handleLimit: function() {
		$(document).on('change', this.options.limit, function(e) {
			var limit = $(this).val();
			mSearch2Config.page = '';
			if (limit == mSearch2Config.start_limit) {
				mSearch2Config.limit = '';
			}
			else {
				mSearch2Config.limit = limit;
			}
			mSearch2.load();
		});
	}

	,handleSelected: function(input) {
		var id = input.prop('id');
		var label = input.parents('label');
		var match = label.html().match(/>(.*?)</);
		if (match && match[1]) {
			var title = match[1].replace(/(\s+$)/, '');
		}
		else {return;}

		if (input.is(':checked')) {
			var elem = this.options.selected_tpl.replace('[[+id]]', id).replace('[[+title]]', title);
			this.selected.find('span').append(elem);
		}
		else {
			$('[data-id="' + id + '"]', this.selected).remove();
		}
		if (this.selected.find('a').length) {this.selected.show();}
		else {this.selected.hide();}
	}

	,load: function(params, callback) {
		if (!params) {
			params = this.getFilters();
		}
		if (mSearch2Config[mSearch2Config.queryVar] != '') {params[mSearch2Config.queryVar] = mSearch2Config[mSearch2Config.queryVar];}
		if (mSearch2Config[mSearch2Config.parentsVar] != '') {params[mSearch2Config.parentsVar] = mSearch2Config[mSearch2Config.parentsVar];}
		if (mSearch2Config.sort != '') {params.sort = mSearch2Config.sort;}
		if (mSearch2Config.tpl != '') {params.tpl = mSearch2Config.tpl;}
		if (mSearch2Config.page > 0) {params.page = mSearch2Config.page;}
		if (mSearch2Config.limit > 0) {params.limit = mSearch2Config.limit;}

		for (var i in this.sliders) {
			if (this.sliders.hasOwnProperty(i) && params[i]) {
				if (this.sliders[i].join(mSearch2Config.values_delimeter) == params[i]) {
					delete params[i];
				}
			}
		}

		this.Hash.set(params);
		params.action = 'filter';
		params.pageId = mSearch2Config.pageId;

		this.beforeLoad();
		params.key = mSearch2Config.key;
		$.post(mSearch2Config.actionUrl, params, function(response) {
			mSearch2.afterLoad();
			if (response.success) {
				mSearch2.Message.success(response.message);
				mSearch2.results.html(response.data.results);
				mSearch2.pagination.html(response.data.pagination);
				mSearch2.setTotal(response.data.total);
				mSearch2.setSuggestions(response.data.suggestions);
				if (response.data.log) {
					$('.mFilterLog').html(response.data.log);
				}
				if (callback && $.isFunction(callback)) {
					callback.call(this, response, params);
				}
			}
			else {
				mSearch2.Message.error(response.message);
			}
		}, 'json');
	}

	,getFilters: function() {
		var data = {};
		$.map(this.filters.serializeArray(), function(n, i) {
			if (data[n['name']]) {
				data[n['name']] += mSearch2Config.values_delimeter + n['value'];
			}
			else {
				data[n['name']] = n['value'];
			}
		});

		return data;
	}

	,setSuggestions: function(suggestions) {
		for (var filter in suggestions) {
			if (suggestions.hasOwnProperty(filter)) {
				var arr = suggestions[filter];
				for (var value in arr) {
					if (arr.hasOwnProperty(value)) {
						var count = arr[value];
						var selector = filter.replace(mSearch2Config.filter_delimeter, "\\" + mSearch2Config.filter_delimeter);
						var input = $('#' + mSearch2.options.prefix + selector, mSearch2.filters).find('[value="' + value + '"]');
						var proptype = input.prop('type');
						if (proptype != 'checkbox' && proptype != 'radio') {continue;}

						var label = $('#' + mSearch2.options.prefix + selector, mSearch2.filters).find('label[for="' + input.prop('id') + '"]');
						var elem = input.parent().find(mSearch2.options.suggestion);
						elem.text(count);

						if (count == 0) {
							input.prop('disabled', true);
							label.addClass(mSearch2.options.disabled_class);
							if (input.is(':checked')) {
								input.prop('checked', false);
								mSearch2.handleSelected(input);
							}
						}
						else {
							input.prop('disabled', false);
							label.removeClass(mSearch2.options.disabled_class);
						}
						if (input.is(':checked')) {elem.hide();}
						else {elem.show();}
					}
				}
			}
		}
	}

	,setTotal: function(total) {
		if (this.total.length != 0) {
			if (!total || total == 0) {
				this.total.parent().hide();
				this.limit.parent().hide();
				this.sort.hide();
				this.total.text(0);
			}
			else {
				this.total.parent().show();
				this.limit.parent().show();
				this.sort.show();
				this.total.text(total);
			}
		}
	}

	,beforeLoad: function() {
		this.results.css('opacity', .5);
		$(this.options.pagination_link).addClass(this.options.active_class);
		this.filters.find('input, select').prop('disabled', true).addClass(this.options.disabled_class);
	}

	,afterLoad: function() {
		this.results.css('opacity', 1);
		this.filters.find('.' + this.options.disabled_class).prop('disabled', false).removeClass(this.options.disabled_class);
	}

	,loadJQUI: function(callback, parameters) {
		$('<link/>', {
			rel: 'stylesheet',
			type: 'text/css',
			href: mSearch2Config.cssUrl + 'redmond/jquery-ui-1.10.4.custom.min.css'
		}).appendTo('head');

		return $.getScript(mSearch2Config.jsUrl + 'lib/jquery-ui-1.10.4.custom.min.js', function() {
			if (typeof callback == 'function') {
				callback(parameters);
			}
		});
	}

};

mSearch2.Form = {
	initialize: function(selector) {
		if (mSearch2Config.autocomplete == '0' || mSearch2Config.autocomplete == 'false') {
			return false;
		}
		else if (!$.ui || !$.ui.autocomplete) {
			return mSearch2.loadJQUI(mSearch2.Form.initialize, selector);
		}

		var cache = {};
		$(selector + ' input[name="' + mSearch2Config.queryVar + '"]').autocomplete({
			source: function(request, callback) {
				if (request.term in cache) {
					callback(cache[request.term]);
					return;
				}
				var data = {
					action: 'search'
					,key: $(selector).data('key')
					,pageId: mSearch2Config.pageId
				};
				data[mSearch2Config.queryVar] = request.term;
				$.post(mSearch2Config.actionUrl, data, function(response) {
					if (response.data.log) {
						$('.mSearchFormLog').html(response.data.log);
					}
					else {
						$('.mSearchFormLog').html('');
					}
					cache[request.term] = response.data.results;
					callback(response.data.results)
				}, 'json');
			}
			,minLength: mSearch2Config.minQuery || 3
			,select: function(event,ui) {
				if (ui.item.url) {
					document.location.href = ui.item.url;
				}
				else {
					setTimeout(function() {
						$(selector).submit();
					}, 100);
				}
			}
		})
		.data("ui-autocomplete")._renderItem = function(ul, item) {
			return $("<li></li>")
				.data("item.autocomplete", item)
				.addClass("mse2-ac-wrapper")
				.append("<a class=\"mse2-ac-link\">"+ item.label + "</a>")
				.appendTo(ul);
		};

		return true;
	}
};

mSearch2.Message = {
	success: function(message) {

	}
	,error: function(message) {
		alert(message);
	}
};

mSearch2.Hash = {
	get: function() {
		var vars = {}, hash, splitter, hashes;
		if (!this.oldbrowser()) {
			var pos = window.location.href.indexOf('?');
			hashes = (pos != -1) ? decodeURIComponent(window.location.href.substr(pos + 1)) : '';
			splitter = '&';
		}
		else {
			hashes = decodeURIComponent(window.location.hash.substr(1));
			splitter = '/';
		}

		if (hashes.length == 0) {return vars;}
		else {hashes = hashes.split(splitter);}

		for (var i in hashes) {
			if (hashes.hasOwnProperty(i)) {
				hash = hashes[i].split('=');
				if (typeof hash[1] == 'undefined') {
					vars['anchor'] = hash[0];
				}
				else {
					vars[hash[0]] = hash[1];
				}
			}
		}
		return vars;
	}
	,set: function(vars) {
		var hash = '';
		for (var i in vars) {
			if (vars.hasOwnProperty(i)) {
				hash += '&' + i + '=' + vars[i];
			}
		}

		if (!this.oldbrowser()) {
			if (hash.length != 0) {
				hash = '?' + hash.substr(1);
			}
			window.history.pushState(hash, '', document.location.pathname + hash);
		}
		else {
			window.location.hash = hash.substr(1);
		}
	}
	,add: function(key, val) {
		var hash = this.get();
		hash[key] = val;
		this.set(hash);
	}
	,remove: function(key) {
		var hash = this.get();
		delete hash[key];
		this.set(hash);
	}
	,clear: function() {
		this.set({});
	}
	,oldbrowser: function() {
		return !(window.history && history.pushState);
	}
};

// Initialize Filters
if ($('#mse2_mfilter').length) {
	if (window.location.hash != '' && mSearch2.Hash.oldbrowser()) {
		var uri = window.location.hash.replace('#', '?');
		window.location.href = document.location.pathname + uri;
	}
	else {
		mSearch2.initialize('#mse2_mfilter');
	}
}
// Initialize Form
if ($('form.msearch2').length && mSearch2Config.autocomplete) {
	mSearch2.Form.initialize('form.msearch2');
}